<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RouteAuthorizationMapTest extends TestCase
{
    private function routeRules(): array
    {
        return require base_path('config/route_permissions.php');
    }

    private function permissionBlueprint(): array
    {
        return require base_path('config/permissions.php');
    }

    public function test_every_route_permission_exists_in_the_canonical_permission_blueprint(): void
    {
        $blueprint = [];
        foreach ($this->permissionBlueprint() as $module => $actions) {
            foreach ($actions as $action) {
                $blueprint[] = strtolower($module . '.' . $action);
            }
        }

        foreach ($this->routeRules() as $method => $rules) {
            foreach ($rules as $pattern => $permissions) {
                foreach ($permissions as $permission) {
                    self::assertContains(
                        strtolower($permission),
                        $blueprint,
                        "{$method} {$pattern} references undefined permission {$permission}"
                    );
                }
            }
        }
    }

    public function test_route_permission_patterns_are_valid_regular_expressions(): void
    {
        foreach ($this->routeRules() as $method => $rules) {
            foreach ($rules as $pattern => $permissions) {
                preg_match($pattern, '/__authorization_probe__');
                self::assertSame(
                    PREG_NO_ERROR,
                    preg_last_error(),
                    "Invalid authorization regex for {$method}: {$pattern}"
                );
                self::assertNotEmpty($permissions);
            }
        }
    }

    public function test_sensitive_routes_have_explicit_authorization_entries(): void
    {
        $rules = $this->routeRules();

        $required = [
            'GET' => [
                '/gatepasses',
                '/gatepasses/123',
                '/visitors',
                '/visitors/123',
                '/visits',
                '/roles',
                '/settings',
                '/settings/users',
                '/reports',
                '/reports/audit-logs',
            ],
            'POST' => [
                '/gatepasses',
                '/gatepasses/123',
                '/gatepasses/123/checkin',
                '/gatepasses/123/checkout',
                '/visitors',
                '/visitors/123/update',
                '/visits/123/checkin',
                '/visits/123/checkout',
                '/badges/123/issue',
                '/badges/123/return',
                '/settings/users',
                '/settings/users/123',
                '/reports/gatepasses/export',
            ],
        ];

        foreach ($required as $method => $uris) {
            foreach ($uris as $uri) {
                $matched = false;
                foreach ($rules[$method] ?? [] as $pattern => $permissions) {
                    if (preg_match($pattern, $uri) === 1) {
                        $matched = true;
                        self::assertNotEmpty($permissions);
                        break;
                    }
                }

                self::assertTrue($matched, "No authorization rule matches {$method} {$uri}");
            }
        }
    }

    public function test_legacy_compatibility_permissions_are_explicitly_scoped(): void
    {
        $rules = $this->routeRules();

        self::assertContains('gatepass.view', $rules['GET']['#^/visitors$#']);
        self::assertContains('gatepass.view_all', $rules['GET']['#^/visitors$#']);
        self::assertContains('gatepass.create', $rules['POST']['#^/visits$#']);
        self::assertContains('gatepass.checkin', $rules['POST']['#^/visits/\\d+/checkin$#']);
        self::assertContains('gatepass.checkout', $rules['POST']['#^/visits/\\d+/checkout$#']);
        self::assertContains('gatepass.checkin', $rules['POST']['#^/badges/\\d+/issue$#']);
        self::assertContains('gatepass.checkout', $rules['POST']['#^/badges/\\d+/return$#']);
        self::assertContains('gatepass.view_all', $rules['GET']['#^/reports$#']);
        self::assertContains('gatepass.view_all', $rules['POST']['#^/reports/(gatepasses|visitors|visits)/export$#']);
    }
}
