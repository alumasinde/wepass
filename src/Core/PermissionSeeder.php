<?php

namespace App\Core;

use PDO;

class PermissionSeeder
{
    public static function seed(PDO $db): void
    {
        $map = require base_path('config/permissions.php');

        $insertModule = $db->prepare(
            "INSERT IGNORE INTO modules (name) VALUES (?)"
        );
        $selectModule = $db->prepare(
            "SELECT id FROM modules WHERE name = ? LIMIT 1"
        );

        $insertAction = $db->prepare(
            "INSERT IGNORE INTO actions (name) VALUES (?)"
        );
        $selectAction = $db->prepare(
            "SELECT id FROM actions WHERE name = ? LIMIT 1"
        );

        $insertPermission = $db->prepare(
            "INSERT IGNORE INTO permissions (module_id, action_id) VALUES (?, ?)"
        );

        foreach ($map as $module => $actions) {
            $insertModule->execute([$module]);

            $selectModule->execute([$module]);
            $moduleId = (int) $selectModule->fetchColumn();

            foreach ($actions as $action) {
                $insertAction->execute([$action]);

                $selectAction->execute([$action]);
                $actionId = (int) $selectAction->fetchColumn();

                $insertPermission->execute([$moduleId, $actionId]);
            }
        }
    }
}
