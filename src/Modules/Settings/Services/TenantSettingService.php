<?php

namespace App\Modules\Settings\Services;

use App\Core\DB;
use PDO;

/**
 * TenantSettingService — per-database isolation model.
 *
 * No tenant_id column needed; each tenant has their own database.
 * The $tenantId constructor parameter and Auth::tenantId() call
 * have been removed — they are meaningless in per-DB isolation.
 */
class TenantSettingService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    /**
     * Get a setting value (auto JSON-decoded).
     * If the key doesn't exist and a non-empty default is provided, it is persisted.
     */
    public function get(string $key, array $default = []): array
    {
        $stmt = $this->db->prepare("
            SELECT config_json FROM tenant_settings WHERE setting_key = :key LIMIT 1
        ");
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            if (!empty($default)) {
                $this->set($key, $default);
            }
            return $default;
        }

        return json_decode($row['config_json'], true) ?? $default;
    }

    /**
     * Save a setting (auto JSON-encoded).
     * FIX: SQL had `(::key, :value)` — double colon typo on :key.
     */
    public function set(string $key, array $value): void
    {
        // FIX: was (::key, :value) — double colon typo; also removed tenant_id column
        $stmt = $this->db->prepare("
            INSERT INTO tenant_settings (setting_key, config_json)
            VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE
                config_json = VALUES(config_json),
                updated_at  = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            ':key'   => $key,
            ':value' => json_encode($value, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function delete(string $key): void
    {
        $this->db->prepare("DELETE FROM tenant_settings WHERE setting_key = :key")
                 ->execute([':key' => $key]);
    }

    public function exists(string $key): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM tenant_settings WHERE setting_key = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        return (bool) $stmt->fetchColumn();
    }

    public function increment(string $key, string $field, int $step = 1): int
    {
        $data          = $this->get($key);
        $current       = (int) ($data[$field] ?? 0);
        $current      += $step;
        $data[$field]  = $current;
        $this->set($key, $data);
        return $current;
    }
}
