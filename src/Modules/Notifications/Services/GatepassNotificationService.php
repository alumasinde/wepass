<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Core\DB;
use PDO;

final class GatepassNotificationService
{
    private PDO $db;
    public function __construct(?PDO $db=null){$this->db=$db??DB::connect();}

    public function notifyCreator(int $gatepassId,string $eventCode,string $title,string $body,array $data=[]):void
    {
        $stmt=$this->db->prepare('SELECT u.id,u.email FROM gatepasses g INNER JOIN users u ON u.id=g.created_by WHERE g.id=? LIMIT 1');
        $stmt->execute([$gatepassId]);$user=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$user)return;
        (new NotificationEventService($this->db))->publishToUser((int)$user['id'],$eventCode,$title,$body,$user['email']??null,null,$data,['in_app','email']);
    }

    public function created(int $gatepassId,string $number,bool $needsApproval):void
    {
        $event=$needsApproval?'gatepass.created_pending_approval':'gatepass.created';
        $title=$needsApproval?'Gatepass awaiting approval':'Gatepass created';
        $body=$needsApproval?"Gatepass {$number} was created and is awaiting approval.":"Gatepass {$number} was created successfully.";
        $this->notifyCreator($gatepassId,$event,$title,$body,['gatepass_id'=>$gatepassId,'gatepass_number'=>$number]);
    }

    public function checkedIn(int $gatepassId,string $number):void{$this->notifyCreator($gatepassId,'gatepass.checked_in','Gatepass checked in',"Gatepass {$number} has been checked in.",['gatepass_id'=>$gatepassId,'gatepass_number'=>$number]);}
    public function checkedOut(int $gatepassId,string $number):void{$this->notifyCreator($gatepassId,'gatepass.checked_out','Gatepass checked out',"Gatepass {$number} has been checked out.",['gatepass_id'=>$gatepassId,'gatepass_number'=>$number]);}
}
