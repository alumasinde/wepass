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
        $db=$this->db??DB::connect();
        $daysAhead=max(0,min(30,$daysAhead));
        $sql="SELECT g.id,g.gatepass_number,g.expected_return_date,u.id user_id,u.email
              FROM gatepasses g INNER JOIN users u ON u.id=g.created_by
              WHERE g.is_returnable=1 AND g.expected_return_date IS NOT NULL
                AND g.expected_return_date<=DATE_ADD(CURDATE(),INTERVAL {$daysAhead} DAY)
                AND g.expected_return_date>=CURDATE()
                AND COALESCE(g.status_id,0)<>(SELECT id FROM gatepass_statuses WHERE code='RETURNED' LIMIT 1)
              ORDER BY g.expected_return_date ASC";
        $rows=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);$queued=0;
        $events=new NotificationEventService($db);
        foreach($rows as $row){
            $date=(string)$row['expected_return_date'];
            $event='gatepass.return_reminder';
            $data=['gatepass_id'=>(int)$row['id'],'gatepass_number'=>$row['gatepass_number'],'expected_return_date'=>$date];
            $events->publishToUser((int)$row['user_id'],$event,'Gatepass return reminder',"Gatepass {$row['gatepass_number']} is due for return on {$date}.",$row['email']??null,null,$data,['in_app','email']);
            $queued++;
        }
        return $queued;
    }
}
