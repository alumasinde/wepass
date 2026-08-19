<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase3GateSecuritySchemaTest extends TestCase
{
    private function migration(): string
    {
        return file_get_contents(base_path('database/016_phase3_gate_security.sql')) ?: '';
    }

    private function permissionsMigration(): string
    {
        return file_get_contents(base_path('database/017_phase3_gate_permissions.sql')) ?: '';
    }

    public function test_gate_security_schema_has_physical_gate_and_device_controls(): void
    {
        $sql = $this->migration();

        foreach ([
            'CREATE TABLE IF NOT EXISTS `gates`',
            'CREATE TABLE IF NOT EXISTS `approved_devices`',
            'CREATE TABLE IF NOT EXISTS `gate_device_assignments`',
            'CREATE TABLE IF NOT EXISTS `gate_scan_events`',
            '`device_secret_hash` char(64)',
            '`revoked_at` datetime',
            '`guard_user_id` bigint unsigned',
            'UNIQUE KEY `uk_scan_request`',
        ] as $required) {
            self::assertStringContainsString($required, $sql);
        }
    }

    public function test_qr_credentials_are_opaque_and_expirable(): void
    {
        $sql = $this->migration();

        self::assertStringContainsString('`qr_token_hash` char(64)', $sql);
        self::assertStringContainsString('`qr_expires_at` datetime', $sql);
        self::assertStringContainsString('`qr_revoked_at` datetime', $sql);
        self::assertStringContainsString('UNIQUE INDEX `uk_gatepass_qr_token_hash`', $sql);
        self::assertStringNotContainsString('qr_token varchar', strtolower($sql));
    }

    public function test_phase3_permissions_cover_gate_device_and_scan_domains(): void
    {
        $sql = $this->permissionsMigration();

        foreach (['gates', 'devices', 'scans', 'approve', 'revoke', 'assign', 'scan', 'export'] as $required) {
            self::assertStringContainsString($required, $sql);
        }
    }
}
