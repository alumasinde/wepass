<?php

declare(strict_types=1);

/**
 * Route-level authorization map.
 *
 * Object-level policies remain in their domain modules. This map closes
 * the common gap where an authenticated user can reach a controller
 * simply because a route only required AuthMiddleware.
 *
 * Each entry is: METHOD => [PCRE path => permission or permissions].
 */
return [
    'GET' => [
        '#^/dashboard(?:/charts)?$#' => ['dashboard.access'],

        '#^/gatepasses$#' => ['gatepass.view'],
        '#^/gatepasses/create$#' => ['gatepass.create'],
        '#^/gatepasses/\d+$#' => ['gatepass.view'],
        '#^/gatepasses/\d+/edit$#' => ['gatepass.update'],

        '#^/visitors$#' => ['visitors.view'],
        '#^/visitors/create$#' => ['visitors.create'],
        '#^/visitors/\d+$#' => ['visitors.view'],
        '#^/visitors/\d+/edit$#' => ['visitors.update'],

        '#^/visits$#' => ['visits.view'],
        '#^/visits/create$#' => ['visits.create'],

        '#^/roles$#' => ['roles.view'],
        '#^/roles/create$#' => ['roles.create'],
        '#^/roles/\d+/edit$#' => ['roles.update'],
        '#^/roles/\d+/permissions$#' => ['roles.assign'],
        '#^/users/\d+/roles$#' => ['roles.assign'],

        '#^/settings$#' => ['settings.view'],
        '#^/settings/company$#' => ['settings.view'],
        '#^/settings/theme$#' => ['settings.view'],
        '#^/settings/gatepass-numbering$#' => ['settings.view'],
        '#^/settings/badge-numbering$#' => ['settings.view'],
        '#^/settings/gatepass-types(?:/\d+/edit)?$#' => ['settings.view'],
        '#^/settings/gatepass-rules$#' => ['settings.view'],
        '#^/settings/departments$#' => ['settings.view'],
        '#^/settings/workflows(?:/\d+(?:/edit|/steps(?:/\d+/edit|/\d+/approvers)?)?)?$#' => ['settings.view'],
        '#^/settings/users$#' => ['users.view'],
        '#^/settings/users/create$#' => ['users.create'],
        '#^/settings/users/\d+/edit$#' => ['users.update'],
        '#^/settings/users/profile$#' => ['dashboard.access'],
        '#^/settings/delegation$#' => ['delegation.view'],

        '#^/reports$#' => ['reports.view'],
        '#^/reports/(gatepasses|visitors|visits)$#' => ['reports.view'],
        '#^/reports/audit-logs$#' => ['audit.view'],
        '#^/reports/(gatepasses|visitors|visits)/export$#' => ['reports.export'],
        '#^/reports/audit-logs/export$#' => ['audit.export'],
    ],
    'POST' => [
        '#^/gatepasses$#' => ['gatepass.create'],
        '#^/gatepasses/\d+$#' => ['gatepass.update'],
        '#^/gatepasses/\d+/delete$#' => ['gatepass.delete'],
        '#^/gatepasses/\d+/checkin$#' => ['gatepass.checkin'],
        '#^/gatepasses/\d+/checkout$#' => ['gatepass.checkout'],

        '#^/visitors$#' => ['visitors.create'],
        '#^/visitors/\d+/update$#' => ['visitors.update'],
        '#^/visitors/\d+/(?:blacklist|unblacklist)$#' => ['visitors.blacklist'],

        '#^/visits$#' => ['visits.create'],
        '#^/visits/\d+/checkin$#' => ['visits.checkin'],
        '#^/visits/\d+/checkout$#' => ['visits.checkout'],

        '#^/badges/\d+/issue$#' => ['badges.issue'],
        '#^/badges/\d+/return$#' => ['badges.return'],

        '#^/roles$#' => ['roles.create'],
        '#^/roles/\d+$#' => ['roles.update'],
        '#^/roles/\d+/delete$#' => ['roles.update'],
        '#^/roles/\d+/permissions$#' => ['roles.assign'],
        '#^/users/\d+/roles$#' => ['roles.assign'],

        '#^/settings/company$#' => ['settings.update'],
        '#^/settings/company/logo$#' => ['settings.update'],
        '#^/settings/theme(?:/reset)?$#' => ['settings.update'],
        '#^/settings/gatepass-numbering$#' => ['settings.update'],
        '#^/settings/badge-numbering$#' => ['settings.update'],
        '#^/settings/gatepass-types(?:/\d+/update|/store)$#' => ['settings.update'],
        '#^/settings/gatepass-rules$#' => ['settings.update'],
        '#^/settings/departments/(?:create|update|toggle|delete)$#' => ['settings.update'],
        '#^/settings/workflows(?:/\d+(?:/update|/steps(?:/\d+/update|/\d+/approvers)?|/assign)?|)$#' => ['settings.update'],
        '#^/settings/users$#' => ['users.create'],
        '#^/settings/users/\d+$#' => ['users.update'],
        '#^/settings/users/profile$#' => ['dashboard.access'],
        '#^/settings/delegation$#' => ['delegation.manage'],
        '#^/settings/delegation/clear$#' => ['delegation.manage'],
    ],
];
