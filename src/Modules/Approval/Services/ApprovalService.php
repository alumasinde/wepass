<?php

namespace App\Modules\Approval\Services;

use App\Core\DB;
use App\Core\Audit;
use App\Core\Mailer;
use App\Modules\Gatepass\Services\GatepassStateService;
use PDO;

class NoEligibleApproverException extends \RuntimeException {}

class ApprovalService
{
    private PDO $db;
    private GatepassStateService $stateService;

    public function __construct()
    {
        $this->db = DB::connect();
        $this->stateService = new GatepassStateService($this->db);
    }

    /** Transition the gatepass using the authoritative Phase 4 state boundary. */
    private function transitionGatepass(int $gatepassId, string $toStatus, string $transitionCode, ?int $actorUserId = null, ?string $reason = null, array $metadata = []): void
    {
        $stmt = $this->db->prepare("SELECT s.code FROM gatepasses g INNER JOIN gatepass_statuses s ON s.id=g.status_id WHERE g.id=? AND g.deleted_at IS NULL FOR UPDATE");
        $stmt->execute([$gatepassId]);
        $fromStatus = $stmt->fetchColumn();
        if (!$fromStatus) {
            throw new \RuntimeException('Gatepass not found or deleted.');
        }

        $this->stateService->transition(
            $gatepassId,
            (string) $fromStatus,
            $toStatus,
            $transitionCode,
            $actorUserId,
            $reason,
            $metadata
        );
    }

    public function approve(int $approvalId, int $userId, ?string $comment = null): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT ga.id,ga.status approval_status,ga.workflow_instance_id,ga.workflow_step_id,gwi.workflow_id,gwi.current_step_order,gwi.status workflow_status,gwi.gatepass_id FROM gatepass_approvals ga INNER JOIN gatepass_workflow_instances gwi ON gwi.id=ga.workflow_instance_id WHERE ga.id=? AND ga.approver_user_id=? FOR UPDATE");
            $stmt->execute([$approvalId,$userId]);
            $approval=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$approval) throw new \RuntimeException('Approval not found.');
            if($approval['approval_status']!=='pending') throw new \RuntimeException('Already processed.');
            if($approval['workflow_status']!=='in_progress') throw new \RuntimeException('Workflow not active.');

            $instanceId=(int)$approval['workflow_instance_id'];
            $currentStep=(int)$approval['current_step_order'];
            $stmt=$this->db->prepare("SELECT step_order,approval_rule FROM workflow_steps WHERE id=? AND workflow_id=?");
            $stmt->execute([$approval['workflow_step_id'],$approval['workflow_id']]);
            $stepRow=$stmt->fetch(PDO::FETCH_ASSOC);
            $stepOrder=(int)($stepRow['step_order']??0);
            $approvalRule=$stepRow['approval_rule']??'all';
            if($stepOrder!==$currentStep) throw new \RuntimeException('Invalid approval step.');

            $this->db->prepare("UPDATE gatepass_approvals SET status='approved',acted_at=NOW(),comments=? WHERE id=?")->execute([$comment,$approvalId]);
            Audit::log('gatepass.approval_approved','gatepass',(int)$approval['gatepass_id'],['approval_id'=>$approvalId,'workflow_instance_id'=>$instanceId,'step'=>$currentStep]);

            if($approvalRule==='any'){
                $this->db->prepare("UPDATE gatepass_approvals ga INNER JOIN workflow_steps ws ON ws.id=ga.workflow_step_id SET ga.status='skipped',ga.acted_at=NOW(),ga.comments='Skipped — already resolved by another approver at this step.' WHERE ga.workflow_instance_id=? AND ws.step_order=? AND ga.status='pending'")->execute([$instanceId,$currentStep]);
                $this->advanceToNextStep($instanceId,$userId);
                $this->db->commit();
                return $instanceId;
            }

            $stmt=$this->db->prepare("SELECT COUNT(*) FROM gatepass_approvals ga INNER JOIN workflow_steps ws ON ws.id=ga.workflow_step_id WHERE ga.workflow_instance_id=? AND ws.step_order=? AND ga.status='pending'");
            $stmt->execute([$instanceId,$currentStep]);
            if((int)$stmt->fetchColumn()>0){$this->db->commit();return $instanceId;}

            $this->advanceToNextStep($instanceId,$userId);
            $this->db->commit();
            return $instanceId;
        } catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function reject(int $approvalId, int $userId, string $comments): int
    {
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare("SELECT ga.id,ga.status,ga.workflow_instance_id,gwi.status workflow_status,gwi.gatepass_id FROM gatepass_approvals ga INNER JOIN gatepass_workflow_instances gwi ON ga.workflow_instance_id=gwi.id WHERE ga.id=? AND ga.approver_user_id=? FOR UPDATE");
            $stmt->execute([$approvalId,$userId]);
            $approval=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$approval)throw new \RuntimeException('Approval not found.');
            if($approval['status']!=='pending')throw new \RuntimeException('Already processed.');
            if($approval['workflow_status']!=='in_progress')throw new \RuntimeException('Workflow not active.');
            $instanceId=(int)$approval['workflow_instance_id'];
            $gatepassId=(int)$approval['gatepass_id'];

            $this->db->prepare("UPDATE gatepass_approvals SET status='rejected',acted_at=NOW(),comments=? WHERE id=?")->execute([$comments,$approvalId]);
            Audit::log('gatepass.approval_rejected','gatepass',$gatepassId,['approval_id'=>$approvalId,'workflow_instance_id'=>$instanceId,'comments'=>$comments]);
            $this->db->prepare("UPDATE gatepass_workflow_instances SET status='rejected',completed_at=NOW() WHERE id=?")->execute([$instanceId]);
            $this->db->prepare("UPDATE gatepass_approvals SET status='skipped',acted_at=NOW(),comments='Skipped — request was rejected by another approver.' WHERE workflow_instance_id=? AND status='pending'")->execute([$instanceId]);

            $this->transitionGatepass($gatepassId,'rejected','APPROVAL_REJECTED',$userId,$comments,['approval_id'=>$approvalId,'workflow_instance_id'=>$instanceId]);
            Audit::log('gatepass.state_transition','gatepass',$gatepassId,['from'=>'current','to'=>'rejected','transition'=>'APPROVAL_REJECTED','actor_user_id'=>$userId]);
            $this->db->commit();
            return $instanceId;
        } catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function advanceToNextStep(int $instanceId, ?int $actorUserId=null): void
    {
        $stmt=$this->db->prepare("SELECT * FROM gatepass_workflow_instances WHERE id=? FOR UPDATE");
        $stmt->execute([$instanceId]);
        $instance=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$instance)throw new \RuntimeException('Workflow instance not found.');
        $currentStep=(int)$instance['current_step_order'];
        $nextStep=$currentStep+1;
        $stmt=$this->db->prepare("SELECT id FROM workflow_steps WHERE workflow_id=? AND step_order=? LIMIT 1");
        $stmt->execute([$instance['workflow_id'],$nextStep]);
        $step=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$step){
            $this->db->prepare("UPDATE gatepass_workflow_instances SET status='approved',completed_at=NOW() WHERE id=?")->execute([$instanceId]);
            $this->transitionGatepass((int)$instance['gatepass_id'],'approved','WORKFLOW_APPROVED',$actorUserId,null,['workflow_instance_id'=>$instanceId,'final_step'=>$currentStep]);
            Audit::log('gatepass.state_transition','gatepass',(int)$instance['gatepass_id'],['from'=>'current','to'=>'approved','transition'=>'WORKFLOW_APPROVED','actor_user_id'=>$actorUserId]);
            return;
        }

        $this->db->prepare("UPDATE gatepass_workflow_instances SET current_step_order=? WHERE id=?")->execute([$nextStep,$instanceId]);
        try{$this->createApprovalsForStep($instanceId,$nextStep);}catch(NoEligibleApproverException $e){
            Audit::log('gatepass.workflow_stalled','gatepass',(int)$instance['gatepass_id'],['workflow_instance_id'=>$instanceId,'stalled_at_step'=>$nextStep,'reason'=>$e->getMessage()]);
            $this->notifyStall((int)$instance['gatepass_id'],$nextStep,$e->getMessage());
        }
    }

    private function notifyStall(int $gatepassId,int $stalledStep,string $reason):void
    {
        try{
            $stmt=$this->db->prepare("SELECT DISTINCT u.email,u.first_name FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN role_permissions rp ON rp.role_id=ur.role_id INNER JOIN permissions p ON p.id=rp.permission_id INNER JOIN modules m ON m.id=p.module_id INNER JOIN actions a ON a.id=p.action_id WHERE u.is_active=1 AND u.email!='' AND m.name='settings' AND a.name='update'");
            $stmt->execute();$recipients=$stmt->fetchAll(PDO::FETCH_ASSOC);if(!$recipients)return;
            $stmt=$this->db->prepare("SELECT gatepass_number FROM gatepasses WHERE id=?");$stmt->execute([$gatepassId]);$gatepassNumber=$stmt->fetchColumn()?:"#{$gatepassId}";
            $subject="Gatepass {$gatepassNumber} approval is stalled";
            $body='<p>Gatepass <strong>'.htmlspecialchars($gatepassNumber).'</strong> has stopped at step '.(int)$stalledStep.' of its approval workflow — no eligible approver could be found.</p><p><strong>Reason:</strong> '.htmlspecialchars($reason).'</p><p>Assign an eligible approver under Settings &rarr; Workflows &rarr; Steps &rarr; Assign Approvers.</p>';
            foreach($recipients as $recipient)Mailer::send($recipient['email'],$subject,$body);
        }catch(\Throwable $e){error_log('ApprovalService::notifyStall failed: '.$e->getMessage());}
    }

    public function getStalledInstances():array
    {
        return $this->db->query("SELECT gwi.id,gwi.gatepass_id,gwi.current_step_order,gwi.workflow_id,ws.name step_name,ws.role_id,ws.department_id step_department_id,g.gatepass_number,g.department_id gatepass_department_id,r.name role_name FROM gatepass_workflow_instances gwi INNER JOIN workflow_steps ws ON ws.workflow_id=gwi.workflow_id AND ws.step_order=gwi.current_step_order INNER JOIN gatepasses g ON g.id=gwi.gatepass_id LEFT JOIN roles r ON r.id=ws.role_id WHERE gwi.status='in_progress' AND NOT EXISTS (SELECT 1 FROM gatepass_approvals ga WHERE ga.workflow_instance_id=gwi.id AND ga.workflow_step_id=ws.id AND ga.status='pending') ORDER BY gwi.started_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

private function createApprovalsForStep(int $instanceId,int $stepOrder):void
{
    $stmt=$this->db->prepare("SELECT ws.id step_id,ws.role_id,ws.step_order,ws.department_id step_dept_id,ws.assignment_type,ws.department_scope FROM workflow_steps ws INNER JOIN gatepass_workflow_instances gwi ON gwi.workflow_id=ws.workflow_id WHERE gwi.id=? AND ws.step_order=? LIMIT 1");
    $stmt->execute([$instanceId,$stepOrder]);$step=$stmt->fetch(PDO::FETCH_ASSOC);if(!$step)throw new \RuntimeException('Workflow step not found.');
    $stmt=$this->db->prepare("SELECT g.department_id,g.created_by FROM gatepasses g INNER JOIN gatepass_workflow_instances gwi ON gwi.gatepass_id=g.id WHERE gwi.id=?");$stmt->execute([$instanceId]);$gatepass=$stmt->fetch(PDO::FETCH_ASSOC)?:['department_id'=>null,'created_by'=>null];$requesterId=$gatepass['created_by']!==null?(int)$gatepass['created_by']:0;
    if($step['assignment_type']==='explicit'){
        $stmt=$this->db->prepare("SELECT u.id FROM workflow_step_approvers wsa INNER JOIN users u ON u.id=wsa.user_id WHERE wsa.workflow_step_id=? AND u.is_active=1 AND u.id!=?");$stmt->execute([$step['step_id'],$requesterId]);$users=$this->substituteDelegates($stmt->fetchAll(PDO::FETCH_ASSOC),$requesterId);
        if(!$users)throw new NoEligibleApproverException("No active users are assigned as approvers for step {$step['step_id']}.");
    }else{
        $scope=$step['department_scope']??'same_as_request';$deptId=null;if($scope==='fixed')$deptId=$step['step_dept_id']?(int)$step['step_dept_id']:null;elseif($scope==='same_as_request')$deptId=$gatepass['department_id']!==null?(int)$gatepass['department_id']:null;
        if($deptId!==null){$stmt=$this->db->prepare("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id WHERE ur.role_id=? AND u.is_active=1 AND u.department_id=? AND u.id!=?");$stmt->execute([$step['role_id'],$deptId,$requesterId]);}
        else{$stmt=$this->db->prepare("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id WHERE ur.role_id=? AND u.is_active=1 AND u.id!=?");$stmt->execute([$step['role_id'],$requesterId]);}
        $users=$this->substituteDelegates($stmt->fetchAll(PDO::FETCH_ASSOC),$requesterId);if(!$users)throw new NoEligibleApproverException("No active users found for role {$step['role_id']}.");
    }
    foreach($users as $user)$this->db->prepare("INSERT INTO gatepass_approvals (workflow_instance_id,workflow_step_id,approver_user_id,status) VALUES (?,?,?,'pending')")->execute([$instanceId,$step['step_id'],$user['id']]);
}

private function substituteDelegates(array $users,int $requesterId):array
{
    if(!$users)return $users;$ids=array_map(static fn($u)=>(int)$u['id'],$users);$placeholders=implode(',',array_fill(0,count($ids),'?'));
    $stmt=$this->db->prepare("SELECT ud.user_id,ud.delegate_user_id FROM user_delegates ud INNER JOIN users u ON u.id=ud.delegate_user_id AND u.is_active=1 WHERE ud.user_id IN ({$placeholders}) AND ud.starts_at<=NOW() AND ud.ends_at>=NOW()");$stmt->execute($ids);$map=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$map[(int)$row['user_id']]=(int)$row['delegate_user_id'];
    if(!$map)return $users;$resolved=[];foreach($ids as $id)$resolved[]=$map[$id]??$id;$resolved=array_values(array_unique(array_filter($resolved,static fn($id)=>$id!==$requesterId)));return array_map(static fn($id)=>['id'=>$id],$resolved);
}

    public function startWorkflow(int $gatepassId,int $workflowId):void
    {
        if($this->hasActiveWorkflow($gatepassId))throw new \Exception('Workflow already started.');
        $stmt=$this->db->prepare("INSERT INTO gatepass_workflow_instances (gatepass_id,workflow_id,current_step_order,status,started_at) VALUES (?, ?, 1, 'in_progress', NOW())");$stmt->execute([$gatepassId,$workflowId]);$instanceId=(int)$this->db->lastInsertId();$this->createApprovalsForStep($instanceId,1);
    }

    public function hasActiveWorkflow(int $gatepassId):bool
    {
        $stmt=$this->db->prepare("SELECT COUNT(*) FROM gatepass_workflow_instances WHERE gatepass_id=? AND status='in_progress'");$stmt->execute([$gatepassId]);return(bool)$stmt->fetchColumn();
    }

    public function getPendingForUser(int $userId):array
    {
        $stmt=$this->db->prepare("SELECT DISTINCT ga.id id,gwi.id workflow_instance_id,g.id gatepass_id,g.gatepass_number,g.purpose,ws.name step_name,gwi.status workflow_status,ga.status approval_status,CONCAT(u.first_name,' ',u.last_name) requested_by_name,gwi.started_at created_at FROM gatepass_approvals ga INNER JOIN gatepass_workflow_instances gwi ON gwi.id=ga.workflow_instance_id INNER JOIN gatepasses g ON g.id=gwi.gatepass_id INNER JOIN workflow_steps ws ON ws.id=ga.workflow_step_id INNER JOIN user_roles ur ON ur.role_id=ws.role_id AND ur.user_id=:user_id INNER JOIN users u ON u.id=g.created_by WHERE ga.status='pending' ORDER BY gwi.started_at DESC");$stmt->execute([':user_id'=>$userId]);return$stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findApproval(int $approvalId,int $userId):?array
    {
        $stmt=$this->db->prepare("SELECT ga.id id,ga.status approval_status,ga.acted_at,gwi.status workflow_status,gwi.started_at created_at,ws.name step_name,g.id gatepass_id,g.gatepass_number,g.purpose,CONCAT(u.first_name,' ',u.last_name) requested_by_name FROM gatepass_approvals ga INNER JOIN gatepass_workflow_instances gwi ON gwi.id=ga.workflow_instance_id INNER JOIN gatepasses g ON g.id=gwi.gatepass_id INNER JOIN workflow_steps ws ON ws.id=ga.workflow_step_id INNER JOIN users u ON u.id=g.created_by WHERE ga.id=:approval_id AND ga.approver_user_id=:user_id LIMIT 1");$stmt->execute([':approval_id'=>$approvalId,':user_id'=>$userId]);$approval=$stmt->fetch(PDO::FETCH_ASSOC);if(!$approval)return null;$itemsStmt=$this->db->prepare("SELECT * FROM gatepass_items WHERE gatepass_id=:gatepass_id");$itemsStmt->execute([':gatepass_id'=>$approval['gatepass_id']]);$approval['items']=$itemsStmt->fetchAll(PDO::FETCH_ASSOC);return$approval;
    }

    public function findUserApproval(int $approvalId,int $userId):?array
    {
        $stmt=$this->db->prepare("SELECT ga.*,g.gatepass_number,g.purpose FROM gatepass_approvals ga INNER JOIN gatepass_workflow_instances gwi ON gwi.id=ga.workflow_instance_id INNER JOIN gatepasses g ON g.id=gwi.gatepass_id WHERE ga.id=? AND ga.approver_user_id=? AND ga.status='pending' LIMIT 1");$stmt->execute([$approvalId,$userId]);return$stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }
}
