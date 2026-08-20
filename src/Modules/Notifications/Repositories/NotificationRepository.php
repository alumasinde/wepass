<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Core\DB;
use PDO;

final class NotificationRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
    }

    public function createInApp(?int $userId, string $eventCode, string $title, string $body, array $data = []): int
    {
        $stmt=$this->db->prepare('INSERT INTO notifications (user_id,event_code,title,body,data_json) VALUES (:user_id,:event_code,:title,:body,:data)');
        $stmt->execute([':user_id'=>$userId,':event_code'=>trim($eventCode),':title'=>trim($title),':body'=>$body,':data'=>$data===[]?null:json_encode($data,JSON_UNESCAPED_SLASHES)]);
        return (int)$this->db->lastInsertId();
    }

    public function queueDelivery(string $idempotencyKey,string $eventCode,string $channel,string $recipient,string $body,?int $notificationId=null,?string $subject=null,array $payload=[]):bool
    {
        $stmt=$this->db->prepare('INSERT INTO notification_outbox (idempotency_key,notification_id,event_code,channel,recipient,subject,body,payload_json) VALUES (:key,:notification_id,:event_code,:channel,:recipient,:subject,:body,:payload) ON DUPLICATE KEY UPDATE idempotency_key=idempotency_key');
        $stmt->execute([':key'=>trim($idempotencyKey),':notification_id'=>$notificationId,':event_code'=>trim($eventCode),':channel'=>strtolower(trim($channel)),':recipient'=>trim($recipient),':subject'=>$subject,':body'=>$body,':payload'=>$payload===[]?null:json_encode($payload,JSON_UNESCAPED_SLASHES)]);
        return $stmt->rowCount()>0;
    }

    public function claimPendingDeliveries(int $limit=50):array
    {
        $limit=max(1,min(100,$limit));
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare("SELECT * FROM notification_outbox WHERE sent_at IS NULL AND available_at<=NOW() AND (locked_at IS NULL OR locked_at<NOW()-INTERVAL 10 MINUTE) ORDER BY id ASC LIMIT {$limit} FOR UPDATE SKIP LOCKED");
            $stmt->execute();$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
            if($rows){$ids=array_column($rows,'id');$placeholders=implode(',',array_fill(0,count($ids),'?'));$lock=$this->db->prepare("UPDATE notification_outbox SET locked_at=NOW(),attempts=attempts+1 WHERE id IN ({$placeholders})");$lock->execute($ids);}
            $this->db->commit();return $rows;
        } catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function markDeliverySent(int $id):bool
    {
        $stmt=$this->db->prepare('UPDATE notification_outbox SET sent_at=NOW(),locked_at=NULL,last_error=NULL WHERE id=:id AND sent_at IS NULL');$stmt->execute([':id'=>$id]);return $stmt->rowCount()>0;
    }

    public function markDeliveryFailed(int $id,string $error):bool
    {
        $stmt=$this->db->prepare('UPDATE notification_outbox SET available_at=DATE_ADD(NOW(),INTERVAL LEAST(3600,POW(2,LEAST(attempts,10))) SECOND),locked_at=NULL,last_error=:error WHERE id=:id AND sent_at IS NULL');$stmt->execute([':id'=>$id,':error'=>mb_substr($error,0,2000)]);return $stmt->rowCount()>0;
    }

    public function unreadForUser(int $userId,int $limit=50):array{$limit=max(1,min(100,$limit));$stmt=$this->db->prepare("SELECT id,event_code,title,body,data_json,read_at,created_at FROM notifications WHERE user_id=:user_id ORDER BY created_at DESC LIMIT {$limit}");$stmt->execute([':user_id'=>$userId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);}
    public function markRead(int $notificationId,int $userId):bool{$stmt=$this->db->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=:id AND user_id=:user_id');$stmt->execute([':id'=>$notificationId,':user_id'=>$userId]);return $stmt->rowCount()>0;}
}
