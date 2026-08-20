<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Core\DB;
use PDO;

final class GatepassReminderService
{
    public function __construct(private readonly ?PDO $db=null){}

    public function queueDueReturnReminders(int $daysAhead=1): int
    {
        $db=$this->db??DB::connect();$daysAhead=max(0,min(30,$daysAhead));
        $returnedId=(int)($db->query("SELECT id FROM gatepass_statuses WHERE code='RETURNED' LIMIT 1")->fetchColumn() ?: 0);
        $stmt=$db->prepare("SELECT g.id,g.gatepass_number,g.expected_return_date,u.id user_id,u.email FROM gatepasses g INNER JOIN users u ON u.id=g.created_by WHERE g.is_returnable=1 AND g.expected_return_date IS NOT NULL AND g.expected_return_date<=DATE_ADD(CURDATE(),INTERVAL {$daysAhead} DAY) AND g.status_id<>? ORDER BY g.expected_return_date ASC");
        $stmt->execute([$returnedId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$queued=0;$events=new NotificationEventService($db);
        foreach($rows as $row){
            $date=(string)$row['expected_return_date'];$overdue=$date<date('Y-m-d');$event=$overdue?'gatepass.return_overdue':'gatepass.return_reminder';$title=$overdue?'Gatepass return overdue':'Gatepass return reminder';$body=$overdue?"Gatepass {$row['gatepass_number']} was due for return on {$date} and is now overdue.":"Gatepass {$row['gatepass_number']} is due for return on {$date}.";
            $data=['gatepass_id'=>(int)$row['id'],'gatepass_number'=>$row['gatepass_number'],'expected_return_date'=>$date];
            $events->publishToUser((int)$row['user_id'],$event,$title,$body,$row['email']??null,null,$data,['in_app','email']);$queued++;
        }
        return $queued;
    }
}
