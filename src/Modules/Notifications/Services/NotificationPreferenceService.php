<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Core\DB;
use PDO;

final class NotificationPreferenceService
{
    private PDO $db;
    private const MANDATORY_EVENTS = ['security.alert','account.security','gate.security'];

    public function __construct(?PDO $db = null)
    {
        $this->db=$db??DB::connect();
    }

    public function listForUser(int $userId): array
    {
        $stmt=$this->db->prepare('SELECT event_code,channel,is_enabled,updated_at FROM notification_preferences WHERE user_id=? ORDER BY event_code,channel');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function set(int $userId,string $eventCode,string $channel,bool $enabled): void
    {
        $eventCode=trim($eventCode);$channel=strtolower(trim($channel));
        if($eventCode===''||$channel==='') throw new \InvalidArgumentException('Event code and channel are required.');
        if(in_array($eventCode,self::MANDATORY_EVENTS,true)) $enabled=true;
        $stmt=$this->db->prepare('INSERT INTO notification_preferences (user_id,event_code,channel,is_enabled,updated_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled),updated_at=NOW()');
        $stmt->execute([$userId,$eventCode,$channel,$enabled?1:0]);
    }

    public function isEnabled(int $userId,string $eventCode,string $channel,bool $default=true): bool
    {
        if(in_array(trim($eventCode),self::MANDATORY_EVENTS,true)) return true;
        $stmt=$this->db->prepare('SELECT is_enabled FROM notification_preferences WHERE user_id=? AND event_code=? AND channel=? LIMIT 1');
        $stmt->execute([$userId,trim($eventCode),strtolower(trim($channel))]);
        $value=$stmt->fetchColumn();
        return $value===false?$default:(bool)$value;
    }
}
