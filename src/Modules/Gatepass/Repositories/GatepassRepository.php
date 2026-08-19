<?php
declare(strict_types=1);

namespace App\Modules\Gatepass\Repositories;

use App\Core\DB;
use App\Core\SearchBuilder;
use App\Modules\Gatepass\Services\GatepassTransitionGuard;
use InvalidArgumentException;
use PDO;

final class GatepassRepository
{
    private PDO $db;
    public function __construct(){ $this->db = DB::connect(); }

    public function create(array $data): int
    {
        $stmt=$this->db->prepare("INSERT INTO gatepasses (visit_id,gatepass_number,gatepass_type_id,status_id,created_by,purpose,is_returnable,expected_return_date,needs_approval,department_id,qr_token_hash,qr_issued_at,qr_expires_at) VALUES (:visit_id,:gatepass_number,:gatepass_type_id,:status_id,:created_by,:purpose,:is_returnable,:expected_return_date,:needs_approval,:department_id,:qr_token_hash,NOW(),:qr_expires_at)");
        $stmt->execute([':visit_id'=>isset($data['visit_id'])?(int)$data['visit_id']:null,':gatepass_number'=>trim($data['gatepass_number']),':gatepass_type_id'=>isset($data['gatepass_type_id'])?(int)$data['gatepass_type_id']:null,':status_id'=>(int)$data['status_id'],':created_by'=>(int)$data['created_by'],':purpose'=>trim($data['purpose']),':is_returnable'=>(int)$data['is_returnable'],':expected_return_date'=>$data['expected_return_date']??null,':needs_approval'=>(int)$data['needs_approval'],':department_id'=>(int)$data['department_id'],':qr_token_hash'=>$data['qr_token_hash']??null,':qr_expires_at'=>$data['qr_expires_at']??null]);
        return (int)$this->db->lastInsertId();
    }
    public function update(int $id,array $data):bool{$allowed=['visit_id','gatepass_type_id','purpose','is_returnable','expected_return_date','needs_approval'];$set=[];$bindings=[':id'=>$id];foreach($allowed as $field){if(!array_key_exists($field,$data))continue;$set[]="{$field}=:{$field}";$bindings[":{$field}"]=match($field){'visit_id','gatepass_type_id'=>$data[$field]?(int)$data[$field]:null,'is_returnable','needs_approval'=>(int)(bool)$data[$field],'purpose'=>trim((string)$data[$field]),'expected_return_date'=>$data[$field]?:null,default=>$data[$field]};}if(!$set)throw new InvalidArgumentException('No updatable fields provided.');if(isset($bindings[':purpose'])&&$bindings[':purpose']==='')throw new InvalidArgumentException('Purpose cannot be empty.');$stmt=$this->db->prepare('UPDATE gatepasses SET '.implode(', ',$set).' WHERE id=:id');$stmt->execute($bindings);return $stmt->rowCount()>0;}
    public function updateQrPath(int $id,string $qrCodePath):bool{$stmt=$this->db->prepare('UPDATE gatepasses SET qr_code_path=:qr WHERE id=:id');$stmt->execute([':qr'=>$qrCodePath,':id'=>$id]);return $stmt->rowCount()>0;}
    public function revokeQr(int $id):bool{$stmt=$this->db->prepare('UPDATE gatepasses SET qr_revoked_at=NOW() WHERE id=:id AND qr_revoked_at IS NULL');$stmt->execute([':id'=>$id]);return $stmt->rowCount()>0;}
    public function updateStatus(int $id,int $statusId):bool{$stmt=$this->db->prepare('UPDATE gatepasses SET status_id=:status_id WHERE id=:id');$stmt->execute([':status_id'=>$statusId,':id'=>$id]);return $stmt->rowCount()>0;}

    public function checkIn(int $gatepassId,int $userId,string $timestamp,int $checkedInStatusId,int $expectedCurrentStatusId):bool
    {
        $this->db->beginTransaction();
        try {
            $current = $this->db->prepare('SELECT s.code FROM gatepasses g INNER JOIN gatepass_statuses s ON s.id=g.status_id WHERE g.id=:id AND g.deleted_at IS NULL FOR UPDATE');
            $current->execute([':id'=>$gatepassId]);
            $fromStatus = $current->fetchColumn();
            if ($fromStatus === false) {
                throw new InvalidArgumentException('Gatepass not found.');
            }

            $toStatus = $this->statusCodeById($checkedInStatusId);
            if (strcasecmp((string)$fromStatus, $this->statusCodeById($expectedCurrentStatusId)) !== 0) {
                throw new RuntimeException('Gatepass state changed concurrently.');
            }
            GatepassTransitionGuard::assert((string)$fromStatus, $toStatus, 'CHECKIN');

            $stmt=$this->db->prepare('UPDATE gatepasses SET actual_in=:timestamp,checked_in_by=:user_id,status_id=:checked_in_status WHERE id=:id AND actual_in IS NULL AND status_id=:expected_status AND deleted_at IS NULL');
            $stmt->execute([':timestamp'=>$timestamp,':user_id'=>$userId,':checked_in_status'=>$checkedInStatusId,':id'=>$gatepassId,':expected_status'=>$expectedCurrentStatusId]);
            if($stmt->rowCount()!==1){throw new RuntimeException('Check-in failed. Gatepass may have been modified concurrently.');}

            $history=$this->db->prepare('INSERT INTO gatepass_state_history (gatepass_id,from_status_id,to_status_id,transition_code,actor_user_id,reason,metadata_json) VALUES (:gatepass_id,:from_status,:to_status,:transition_code,:actor,:reason,:metadata)');
            $history->execute([':gatepass_id'=>$gatepassId,':from_status'=>$expectedCurrentStatusId,':to_status'=>$checkedInStatusId,':transition_code'=>'CHECKIN',':actor'=>$userId,':reason'=>null,':metadata'=>json_encode(['actual_in'=>$timestamp],JSON_UNESCAPED_SLASHES)]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if($this->db->inTransaction()){$this->db->rollBack();}
            throw $e;
        }
    }

    public function checkOut(int $gatepassId,int $userId,string $timestamp,int $checkedOutStatusId,int $expectedCurrentStatusId):bool
    {
        $this->db->beginTransaction();
        try {
            $current = $this->db->prepare('SELECT s.code FROM gatepasses g INNER JOIN gatepass_statuses s ON s.id=g.status_id WHERE g.id=:id AND g.deleted_at IS NULL FOR UPDATE');
            $current->execute([':id'=>$gatepassId]);
            $fromStatus = $current->fetchColumn();
            if ($fromStatus === false) {
                throw new InvalidArgumentException('Gatepass not found.');
            }

            $toStatus = $this->statusCodeById($checkedOutStatusId);
            if (strcasecmp((string)$fromStatus, $this->statusCodeById($expectedCurrentStatusId)) !== 0) {
                throw new RuntimeException('Gatepass state changed concurrently.');
            }
            GatepassTransitionGuard::assert((string)$fromStatus, $toStatus, 'CHECKOUT');

            $stmt=$this->db->prepare('UPDATE gatepasses SET actual_out=:timestamp,checked_out_by=:user_id,status_id=:status_id WHERE id=:id AND actual_out IS NULL AND status_id=:expected_status AND deleted_at IS NULL');
            $stmt->execute([':timestamp'=>$timestamp,':user_id'=>$userId,':status_id'=>$checkedOutStatusId,':id'=>$gatepassId,':expected_status'=>$expectedCurrentStatusId]);
            if($stmt->rowCount()!==1){throw new RuntimeException('Checkout failed. Gatepass may have been modified concurrently.');}

            $history=$this->db->prepare('INSERT INTO gatepass_state_history (gatepass_id,from_status_id,to_status_id,transition_code,actor_user_id,reason,metadata_json) VALUES (:gatepass_id,:from_status,:to_status,:transition_code,:actor,:reason,:metadata)');
            $history->execute([':gatepass_id'=>$gatepassId,':from_status'=>$expectedCurrentStatusId,':to_status'=>$checkedOutStatusId,':transition_code'=>'CHECKOUT',':actor'=>$userId,':reason'=>null,':metadata'=>json_encode(['actual_out'=>$timestamp],JSON_UNESCAPED_SLASHES)]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if($this->db->inTransaction()){$this->db->rollBack();}
            throw $e;
        }
    }

    private function statusCodeById(int $statusId): string
    {
        $stmt=$this->db->prepare('SELECT code FROM gatepass_statuses WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$statusId]);
        $code=$stmt->fetchColumn();
        if($code===false){throw new InvalidArgumentException('Unknown gatepass status.');}
        return strtoupper((string)$code);
    }

    public function findById(int $id):?array{$stmt=$this->db->prepare("SELECT g.id,g.gatepass_number,g.visit_id,g.gatepass_type_id,g.status_id,g.department_id,g.checked_in_by,g.checked_out_by,g.actual_in,g.actual_out,g.created_by,g.created_at,g.purpose,g.is_returnable,g.expected_return_date,g.actual_return_date,g.is_fully_returned,g.needs_approval,g.qr_token_hash,g.qr_expires_at,g.qr_issued_at,g.qr_revoked_at,g.qr_code_path,s.name status_name,s.code status_code,gt.name gatepass_type_name,gt.type_code,gt.allowed_actions,gt.direction,u.first_name created_by_first_name,u.last_name created_by_last_name FROM gatepasses g INNER JOIN gatepass_statuses s ON s.id=g.status_id INNER JOIN gatepass_types gt ON gt.id=g.gatepass_type_id LEFT JOIN users u ON u.id=g.created_by WHERE g.id=:id AND g.deleted_at IS NULL LIMIT 1");$stmt->execute([':id'=>$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)return null;$row['status_code']=strtoupper($row['status_code']??'');$row['type_code']=strtoupper($row['type_code']??'');return $row;}
    public function findByNumber(string $number):?array{$stmt=$this->db->prepare("SELECT g.*,s.name status_name,s.code status_code,gt.name gatepass_type_name,gt.type_code,gt.allowed_actions,gt.direction,u.first_name,u.last_name FROM gatepasses g INNER JOIN gatepass_statuses s ON s.id=g.status_id INNER JOIN gatepass_types gt ON gt.id=g.gatepass_type_id LEFT JOIN users u ON u.id=g.created_by WHERE g.gatepass_number=:number AND g.deleted_at IS NULL LIMIT 1");$stmt->execute([':number'=>$number]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)return null;$row['status_code']=strtoupper($row['status_code']??'');$row['type_code']=strtoupper($row['type_code']??'');return $row;}
    public function find(int $id):?array{return $this->findById($id);}
    public function findAll():array{$sql="SELECT g.id,g.gatepass_number,g.actual_in,g.actual_out,g.is_returnable,g.needs_approval,g.purpose,g.created_at,gs.name status_name,gs.code status_code,gt.name gatepass_type_name,gt.type_code,gt.allowed_actions,gt.direction,d.name department_name,CONCAT(v.first_name,' ',v.last_name) visitor_name,vc.name company,CONCAT(u.first_name,' ',u.last_name) requested_by FROM gatepasses g LEFT JOIN visits vi ON vi.id=g.visit_id LEFT JOIN visitors v ON v.id=vi.visitor_id LEFT JOIN visitor_companies vc ON vc.id=v.company_id LEFT JOIN gatepass_statuses gs ON gs.id=g.status_id LEFT JOIN gatepass_types gt ON gt.id=g.gatepass_type_id LEFT JOIN departments d ON d.id=g.department_id LEFT JOIN users u ON u.id=g.created_by WHERE g.deleted_at IS NULL";$bindings=[];$sql=SearchBuilder::apply($sql,['g.gatepass_number','g.purpose','d.name','v.first_name','v.last_name','vc.name','u.first_name','u.last_name'],$bindings);$sql.=' ORDER BY g.created_at DESC';$stmt=$this->db->prepare($sql);$stmt->execute($bindings);return $stmt->fetchAll(PDO::FETCH_ASSOC);}
    public function findAllByDepartment(int $departmentId):array{$stmt=$this->db->prepare("SELECT g.id,g.gatepass_number,g.actual_in,g.actual_out,g.is_returnable,g.needs_approval,g.purpose,g.created_at,gs.name status_name,gs.code status_code,gt.name gatepass_type_name,gt.type_code,gt.allowed_actions,gt.direction,d.name department_name,CONCAT(v.first_name,' ',v.last_name) visitor_name,vc.name company,CONCAT(u.first_name,' ',u.last_name) requested_by FROM gatepasses g LEFT JOIN visits vi ON vi.id=g.visit_id LEFT JOIN visitors v ON v.id=vi.visitor_id LEFT JOIN visitor_companies vc ON vc.id=v.company_id LEFT JOIN gatepass_statuses gs ON gs.id=g.status_id LEFT JOIN gatepass_types gt ON gt.id=g.gatepass_type_id LEFT JOIN departments d ON d.id=g.department_id LEFT JOIN users u ON u.id=g.created_by WHERE g.department_id=:department_id AND g.deleted_at IS NULL ORDER BY g.created_at DESC");$stmt->execute([':department_id'=>$departmentId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);}
    public function getWorkflowIdFromType(int $gatepassTypeId):?int{$stmt=$this->db->prepare("SELECT gt.workflow_id FROM gatepass_types gt JOIN workflows w ON w.id=gt.workflow_id AND w.is_active=1 WHERE gt.id=:gatepass_type_id AND gt.is_active=1 LIMIT 1");$stmt->execute([':gatepass_type_id'=>$gatepassTypeId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?(int)$row['workflow_id']:null;}
    public function typeRequiresApproval(int $gatepassTypeId):bool{$stmt=$this->db->prepare('SELECT requires_approval FROM gatepass_types WHERE id=:id AND is_active=1 LIMIT 1');$stmt->execute([':id'=>$gatepassTypeId]);$value=$stmt->fetchColumn();return $value===false?true:(bool)$value;}
    public function delete(int $id):bool{$stmt=$this->db->prepare('UPDATE gatepasses SET deleted_at=NOW() WHERE id=:id AND deleted_at IS NULL');$stmt->execute([':id'=>$id]);return $stmt->rowCount()>0;}
}
