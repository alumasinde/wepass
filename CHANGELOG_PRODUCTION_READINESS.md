# Production-Readiness Patch — Changelog

Applied on top of the existing glee-v2 codebase. `vendor/` was stripped
from this zip (unchanged, large) — run `composer install` after
unpacking.

## ⚠️ Manual steps required

1. `composer install` (restores `vendor/`, untouched by this patch).
2. Run the new migration on **every tenant database**:
   ```
   mysql -u youruser -p your_tenant_db < database/005_production_readiness.sql
   ```
3. `config/config.ini.example` was **missing from the repo** even though
   `README.md` references it — it's been recreated here with every key
   the code actually reads, plus new `[security]` and `[mail]` sections.
   Copy it to `config/config.ini` and fill in real values:
   ```
   cp config/config.ini.example config/config.ini
   ```
   If you already have a working `config/config.ini`, just add the new
   `[security]`, `[mail]`, and `[qr]` sections from the example — don't
   overwrite your existing DB credentials.
4. Set `[app] debug = false` in `config/config.ini` for staging/production.
5. Decide on `[mail] driver`: leave as `log` until you have real SMTP
   credentials (nothing gets sent, everything lands in the `mail_log`
   table and `storage/mail/*.html` for inspection). Switch to `smtp`
   and fill in `host`/`username`/`password` when ready.

## Fixed

- **Hardcoded DB credentials removed** — `Core/DB.php` had a real
  username/password baked in as fallback defaults. It now fails
  loudly with a clear config error instead of silently connecting
  with leaked credentials if `[database]` is misconfigured.
- **Password reset emails were never sent** — `AuthService` had the
  mail dispatch commented out. Now wired to the new `Mailer` class.
- **`Auth::logout()` didn't destroy the session** — only unset a few
  session keys, unlike `AuthService::logout()`. Now consistent
  (regenerates cookie, calls `session_destroy()`). Was dead code
  (nothing called it) but left as a landmine for future use.
- **`GateScanController` leaked raw exception messages** to the gate
  scanner UI. Now logs the real error server-side and shows a
  generic message unless `app.debug` is on.
- **`config/config.ini.example` was missing**, breaking the setup
  steps in your own README.
- **`bootstrap/helpers.php` was dead code** — defined only
  `base_path()` (a duplicate of the one in `bootstrap/app.php`) and
  was never `require`d anywhere. Rewritten with real helpers and
  wired into the boot sequence.
- **`PermissionMiddleware` was dead code** — defined but never
  attached to any route, so RBAC existed in the DB/session but
  wasn't enforced anywhere. Now wired into `routes/web.php` for
  every mutating action that has a matching seeded permission key.

## Added

- `App\Core\RateLimiter` — reusable, DB-backed throttle (`rate_limits`
  table). Survives cookie/session clearing, unlike the old
  session-only login counter.
- `App\Middleware\RateLimitMiddleware` — attach to any route:
  `[[RateLimitMiddleware::class, 'bucket-name', maxAttempts, decaySeconds]]`.
  Wired onto `/login`, `/forgot-password`, `/reset-password`, and
  `/gatepasses/scan`. Thresholds are tunable in `config.ini [security]`
  without touching code.
- `App\Core\Mailer` — dependency-free SMTP client (no new Composer
  package — matches the project's "no deps unless deliberate" rule).
  `log` driver for safe local/dev testing, `smtp` driver for real
  sending. Every attempt is recorded in the new `mail_log` table.
- `Core\Router` now supports parameterized middleware:
  `[SomeMiddleware::class, 'arg1', 'arg2']` alongside the existing
  plain-class-name form — fully backward compatible.
- Hardened `QRService` — cURL with timeout + retry/backoff, caches
  every generated PNG to `public/uploads/qrcodes/`, so gate scanning
  never depends on the QR microservice being reachable (only
  gatepass *creation* does). Wired into `GatepassService::create()`.
  New `gatepasses.qr_code_path` column added by the migration.
- `GatepassRepository::updateQrPath()` — small dedicated method to
  persist the cached QR path.
- Real reusable helpers in `bootstrap/helpers.php`: `e()`,
  `csrf_token()`, `csrf_field()`, `old()`, `flash()`, `redirect_with()`,
  `asset()`, `can()`, `format_date()`, `str_limit()`, `active_route()`.
- `database/005_production_readiness.sql` — incremental migration
  (`rate_limits`, `mail_log` tables; `qr_code_path` column), following
  the existing numbered-migration convention.

## Permission coverage (see `routes/web.php` footer note)

Gated with `PermissionMiddleware`: `gatepass.{create,update,delete,
approve,checkin,checkout}`, `visitors.{create,update,blacklist}`,
`users.{create,update}`, `roles.{create,update,assign}`,
`settings.update`, `audit.view` — all match keys already seeded from
`config/permissions.php`, so nothing is locked out by a typo'd key.

Left as auth-only (login required, no fine-grained permission check):
all GET/list/show routes, plus the **gatepass-types, departments, and
workflows modules**, which have no seeded permission module at all
yet in `config/permissions.php`. Add modules for those and reseed
(`PermissionSeeder::seed()`) if you want them locked down too — I
deliberately didn't guess permission key names for those, since a
wrong guess means nobody (including admins) can reach the page.

## Still not done (flagging honestly, not hiding it)

- No persistent brute-force lockout tied to IP+account combined
  (current `RateLimiter` keys by user id or IP — good enough for
  most deployments, but doesn't fully stop a distributed attempt).
- No automated backup strategy for tenant databases — that's an ops
  decision (cron + `mysqldump`, or your hosting provider's managed
  backups), not something to bake into the app.
- No test suite (`composer.json` only has static analysis tools).
- No CSP header — only `X-Frame-Options` and `X-Content-Type-Options`
  are set (on login only). Worth adding project-wide if you serve
  any user-controlled HTML.

---

# Update 2: Master DB, super admin, and "create tenant via UI"

## ⚠️ New manual steps

1. Re-run `composer install` if you haven't since the first patch.
2. **Add a `[mysql]` section to your `config.ini`** — this is new.
   It's the admin/root-level DB account (the only one with
   `CREATE DATABASE` privilege), used by `database/migrate.php`,
   `database/Seeder.php`, and the new tenant-provisioning flow.
   Keep `[database]` as a separate, least-privilege account scoped
   to just the active tenant DB if you can — don't reuse the same
   account for both.
3. Bootstrap the master database and your own super admin account:
   ```
   php database/migrate.php --email=you@yourcompany.com --name="Your Name"
   ```
   Omit `--password=` and it generates one and prints it once —
   save it immediately, it's not shown again. Safe to re-run.
4. Log in at `/master/login` on whichever deployment has a **blank**
   `[tenant] code` in its `config.ini` (this is your platform/admin
   panel — separate from any client's install). From there:
   Tenants → New Tenant.
5. **Install `php-mbstring` and `php-curl`** if your server doesn't
   already have them — the app uses `mb_strtolower()` throughout
   (login, tenant provisioning) and `QRService` needs cURL.

## What "create tenant via UI" actually does — and doesn't do

`TenantService::provisionTenant()` (tested live end-to-end against
a real MySQL instance, including rollback and validation paths):

1. Validates the tenant code is unique and well-formed.
2. Creates the tenant's own database.
3. Runs `001_schema.sql` then `002_seed_reference.sql` against it.
4. Creates **that tenant's own first admin account** from the form
   input — never a shared or reused account.
5. Seeds default `tenant_settings`.
6. Registers the tenant in `glee_master.tenants`.
7. If any step fails, the partially-created database is dropped —
   verified no orphaned `glee_tenant_*` databases are left behind
   on a rejected/failed attempt.

**What it doesn't do**: spin up a new web server, vhost, or
subdomain for the client. That's still a manual ops step — copy
the codebase, point a docroot at it, drop in a `config.ini`. The
tenant list page shows a ready-to-copy `config.ini` snippet
(tenant code/name/plan/database name) right after you create a
tenant, to make that step faster.

## Added

- `master_admins` table (in `master.sql`) — platform-level super
  admin accounts, separate from any tenant's `users` table.
- `database/migrate.php` — master-DB-only bootstrap CLI (same
  config.ini-driven, idempotent pattern as `Seeder.php`). Never
  touches a tenant database; never hardcodes a password.
- `database/002_seed_reference.sql` — reference/lookup data only,
  split out of `002_seed.sql`, which mixed in a personal admin
  account (real email + live password hash) and demo visitor
  records. `002_seed.sql` now has a loud warning header; use the
  reference version for any new tenant, CLI or UI.
- `App\Core\DB::master()` — always connects to `glee_master`
  regardless of which tenant DB the active install targets.
  **Fixes a real bug**: `TenantRepository` was querying the
  `tenants` table through the tenant-scoped connection, where it
  has never existed — logo upload/lookup would have failed on any
  real tenant deployment.
- `App\Core\DB::adminConnection()` — ad-hoc admin/root connection
  for provisioning a tenant database that doesn't exist yet.
- `App\Core\DB::firstNonEmpty()` (private) — fixes a subtler bug:
  `config('master_db.username', config('mysql.username', ''))`
  doesn't actually fall back when `[master_db] username` is present
  but blank in the ini file — `config()`'s default only applies
  when a key is entirely absent, not when it's `""`. Same bug
  existed in the original `bootstrap/app.php` inline connection.
- `App\Modules\MasterAdmin\*` — repository, `MasterAuthService`,
  `MasterLoginController`/`MasterLogoutController`, login view.
  Deliberately reuses the same `$_SESSION['user']`/`is_super_admin`
  shape as the existing tenant `AuthService`, so `AuthMiddleware`
  and `Permission::can()`'s existing super-admin bypass work with
  no changes. Refuses to operate at all on a deployment that has a
  `[tenant] code` set (defense in depth).
- `App\Middleware\SuperAdminMiddleware` — guards `/master/tenants/*`.
- `TenantRepository` extended with `all()`, `create()`, `codeExists()`.
- `TenantController` extended with `index`/`create`/`store` for the
  tenant management UI; `TenantService::provisionTenant()` holds
  the actual logic.
- Routes: `/master/login`, `/master/logout`, `/master/tenants`,
  `/master/tenants/create`, `POST /master/tenants` — rate-limited
  login, `AuthMiddleware` + `SuperAdminMiddleware` on management
  routes.
- Views: `Tenant::index` (list, shows the config.ini snippet once
  after creating a tenant), `Tenant::create` (form).

## Fixed along the way

- Tenant login page never displayed its own flash/error messages
  (`LoginController` passes `flash`, the view checked `$error`,
  which was never set — failed-login messages were silently
  swallowed). Fixed in both the tenant and new master login views.
- `TenantService::uploadAndSaveLogo()` used `mkdir($path, 755, true)`
  — `755` decimal, not octal `0755` — producing wrong permissions.
  Fixed to `0755`.

---

# Update 3: Dynamic domain resolution + badge/gatepass numbering

## ⚠️ New manual steps

1. Re-run `composer install` if you haven't since the last update.
2. Re-run `php database/migrate.php` — `master.sql` gained a new
   `custom_domain` column on `tenants` (idempotent, safe to re-run;
   your existing `master_admins` account is untouched).
3. **To turn on dynamic domain mode**, add to `config.ini`:
   ```
   [platform]
   base_domain     = "gpms.co.ke"
   admin_subdomain = "manage"
   ```
   Point DNS: a wildcard `*.gpms.co.ke` A/CNAME record at this one
   deployment, and `gpms.co.ke`/`www` too (reserved for your future
   public site). Leave `base_domain` blank to keep the old
   per-deployment `config.ini` model unchanged — nothing breaks for
   any existing single-tenant install.
4. **TLS is the one piece this can't automate**: a wildcard cert
   (`*.gpms.co.ke`) covers every subdomain, but each client's
   *custom* domain (`gatepass.gleenairobi.co.ke`) needs its own
   cert. Standard `certbot`/Apache/Nginx can't issue those on the
   fly per Host header. **Recommend switching the web server to
   Caddy**, which has built-in on-demand TLS (issues + renews a
   cert automatically the first time a new Host is seen) — this is
   the piece that makes arbitrary client-owned custom domains
   actually work in production without you manually running
   certbot for every client.

## How resolution works now

One deployment serves every tenant. Per request, `bootstrap/app.php`
reads the `Host` header:

- `{code}.gpms.co.ke` → looked up by `tenants.code`
- `manage.gpms.co.ke` → the platform/tenant-management panel
- `gpms.co.ke` / `www.gpms.co.ke` → reserved placeholder (your
  public site goes here later — swap the placeholder HTML in
  `bootstrap/app.php` when you build it)
- anything else → looked up by `tenants.custom_domain` (exact match)
- no match → clean 404 before the router or any DB connection

**Tested live** against real MySQL, simulating 7 different `Host`
headers: root domain, `www`, admin subdomain, a tenant's own
subdomain, that same tenant's custom domain (both correctly
resolved to the same tenant), an unknown subdomain, and a
completely unrelated domain. Also verified `DB::connect()` actually
lands on the right tenant database end-to-end, and that legacy
static mode (`[platform] base_domain` left blank) still resolves
exactly as before — this is additive, not a breaking migration.

**Also fixed while rewriting this block**: the old bootstrap had a
"load tenant settings into runtime config" step that queried the
`tenant_settings` table (which only exists per-tenant-DB) through
the **master** PDO connection — every real settings consumer
already queries it correctly elsewhere, so this was dead weight.
Worse: the resulting exception was silently swallowed in
production, or **killed every single request** when
`app.debug=true`. Removed.

## Added

- `App\Core\TenantContext` — resolved once per request (admin host
  / public host / specific tenant / unresolved), read by
  `MasterLoginController` and available anywhere instead of
  re-deriving from `config()`.
- `tenants.custom_domain` column (unique, nullable) + `TenantRepository::findForHost()`,
  `findActiveByCustomDomain()`, `customDomainExists()`.
- `[platform]` section in `config.ini.example` documenting both modes.
- Tenant creation form: live subdomain preview as you type the
  code, optional custom-domain field (only shown in dynamic mode).
- Tenant list: shows each tenant's live URL (clickable) instead of
  just the internal database name, when dynamic mode is active.
- `TenantService::provisionTenant()` now validates and stores
  `custom_domain`, and returns a mode-aware message — dynamic mode
  says "already live at https://code.gpms.co.ke", legacy mode still
  returns the `config.ini` snippet.

## Gatepass/badge numbering — fixed and extended

Found while implementing badge numbering: gatepass numbering was
**already** DB+UI configurable (`Settings → Gatepass Numbering`,
atomic row-locked sequence) — but `TenantService`'s own
provisioning seed wrote the wrong `tenant_settings` key in the
wrong shape, so new tenants silently never got a working default.
Badges had no configuration at all — hardcoded random hex.

- Fixed the seed shape in both `TenantService::seedDefaultTenantSettings()`
  and `Seeder.php`'s equivalent — verified live: provisioned a
  tenant, confirmed `tenant_settings` has the correct
  `gatepass_numbering`/`badge_numbering` keys in the shape
  `GatepassService`/`BadgeService` actually read.
- Built the same DB-configurable numbering for badges — new
  `Settings → Badge Numbering` page (prefix, sequential-with-padding
  or random mode, yearly reset). `BadgeService::generateBadgeCode()`
  uses the same row-locked read-modify-write pattern as gatepass
  numbering. **Fixed a real ordering bug along the way**: the badge
  code was originally generated *before* `beginTransaction()`, so
  the `FOR UPDATE` row lock I added would've been released
  immediately under autocommit — no actual protection against two
  concurrent badge issues reading the same sequence number. Moved
  generation inside the transaction, matching the gatepass pattern.
- **Live-tested**: issued 3 real badges through `BadgeService::issue()`
  with a custom `VIS-2026-0001` format saved through the actual
  settings service — sequence persisted correctly across calls.
- Restyled the gatepass-numbering settings page too (was bare
  unstyled HTML with no CSS classes at all).
- `Seeder.php` (CLI dev-tenant provisioning) now uses
  `002_seed_reference.sql` instead of the personal/demo-data seed,
  and creates a real tenant admin from a new `[tenant_admin]`
  config section (or generates a password if none given) instead
  of leaving the tenant with zero users.

## Still not done / worth knowing

- The public marketing site is a placeholder (inline HTML in
  `bootstrap/app.php`) — swap it for the real thing when you build
  it; it's one `echo` block, easy to find.
- No custom-domain ownership verification (TXT record check, etc.)
  — currently trust-based, since only an authenticated super admin
  can set a `custom_domain`. Fine for how you're operating today;
  worth adding if you ever let tenants self-serve their own domain.
- Caddy's on-demand TLS (recommended above) does need a small
  allowlist check against your own tenant registry before it'll
  issue a cert for an arbitrary Host — otherwise anyone could point
  DNS at your IP and get you to mint them a cert. Caddy supports an
  `ask` endpoint for exactly this; happy to build that small
  verification endpoint when you're ready to set up the web server.



---

# Update 4: Critical security pass — CSRF, object-level authorization, tenant/session binding, soft deletes

## ⚠️ New manual steps

1. Run the new migration on **every tenant database**:
   ```
   mysql -u youruser -p your_tenant_db < database/006_soft_deletes.sql
   ```
   (Fresh tenants provisioned via `TenantService::provisionTenant()` or `Seeder.php` already get this from the updated `001_schema.sql` — no separate step needed for new tenants.)
2. Confirm `config/config.ini` has `[app] debug = false` (fixed in this pass, but if you have per-environment config.ini files elsewhere, check those too).
3. **Rotate your MySQL password.** It was sitting in plaintext in a comment at the bottom of `config.ini` (separate from the `[mysql]`/`[master_db]`/`[database]` fields themselves) and had already left the file during a review. Treat it as burned regardless of where the review happened.
4. If you added your own custom controllers/views since the last patch, check any `<form method="POST">` you wrote renders `<?= csrf_field() ?>` inside the form — every POST request is now verified by a global CSRF check (see below), so a form missing the field will get a 419 error.

## Fixed — CSRF

- `CSRFMiddleware` was defined but never attached to any route, and even where attached would have checked the wrong session key/field name (`_token` vs. the `csrf_token` every form actually sends). Rewritten to use the real key/field name with a timing-safe `hash_equals` comparison.
- Wired into `Router::dispatch()` so it runs on **every** non-GET request automatically, before any route-specific middleware — no route can ship without CSRF protection by omission going forward.
- 27 forms across 21 view files were missing the CSRF field entirely (Settings → Users/Company/Workflows, Roles → Permissions, Visitors, Badges, Visits, Gatepass checkin/checkout/delete, forgot/reset password) — these silently had zero CSRF protection even though other forms in the same app did. All patched.
- Removed a dead duplicate route-registration block for `/gatepasses/{id}` (edit/update/delete) — `Router::add()` keys routes by exact path, so the later block was silently overwriting the earlier one; only one of the two ever actually ran.

## Fixed — object-level Gatepass authorization

- `GatepassPolicy::update()` already correctly restricted edits to "the creator, while still PENDING" — but `GatepassController` never called it. `GatepassPolicy::delete()` had no scoping at all. Neither `show()` nor `edit()`/`update()`/`delete()` checked department or ownership; only the coarse `gatepass.{update,delete}` permission key was enforced at the route level.
- Rewrote `GatepassPolicy` with real `view()`, `update()`, and `delete()` checks (ownership + department + status), and wired all three into `GatepassController`. `delete()` is deliberately stricter than `update()` — it's blocked once a gatepass has been checked in, since that represents a real physical event and destroying the record would destroy part of the audit trail.
- Replaced the hardcoded `in_array($role, ['admin', 'General Manager', 'superadmin'])` check in `GatepassService::list()`, and an equivalent dead branch in `ApprovalPolicy`, with a new DB-seeded `gatepass.view_all` permission — cross-department visibility is now assigned the same way every other permission in the app is, through the Roles UI, with no role name hardcoded in PHP.
- Found and fixed a real latent bug this surfaced: `department_id` was never selected in `UserRepository::findActiveByEmail()` or stored in `$_SESSION['user']` at login, so every session-based department check anywhere in the app (including the new policy) was silently comparing against `null`. Now populated at login.

## Fixed — tenant/session binding

- `AuthMiddleware` previously only checked "is somebody logged in" (`isset($_SESSION['user'])`), never "logged in to the tenant this request actually resolved to." Session storage is shared across the whole multi-tenant deployment, and `DB::connect()` picks a different physical database purely from the current request's `Host` header — so a session established on one tenant's subdomain, replayed against a different tenant's subdomain, would have been accepted as authenticated there too.
- `AuthMiddleware` now compares `$_SESSION['tenant']['code']` against `TenantContext::tenant()['code']` (or `config('tenant.code')` in legacy static mode) on every request and forces re-login on a mismatch. No-ops safely for the master-admin host and legacy single-tenant deployments, where there's nothing to bind against.

## Fixed — hard-delete cascade destroying the audit trail

- `gatepass_workflow_instances` and `gatepass_approvals` are `ON DELETE CASCADE` from `gatepasses`. `GatepassRepository::delete()` issued a real `DELETE`, so deleting a gatepass permanently erased its entire approval history along with it.
- New `deleted_at` column on `gatepasses` (`database/006_soft_deletes.sql`, and added directly to `001_schema.sql` for fresh installs). `delete()` now sets `deleted_at` instead of removing the row; `findById`, `findByNumber`, `findAll`, and `findAllByDepartment` all exclude soft-deleted rows. The row — and its approval history — stays in the database, just hidden from normal use.
- `GatepassService::delete()` no longer hard-deletes the gatepass's line items first; they now survive alongside the soft-deleted parent instead of being destroyed pre-emptively.

## Fixed — debug flag

- `config/config.ini` had `[app] debug = true` in a config file labeled production. With debug on, the global exception handler in `bootstrap/app.php` prints the full exception message and stack trace (file paths, query structure) to any visitor on an uncaught error. Set to `false`.

## Still not done / worth knowing

- `config/workflow.php` is an empty, unused file (real workflow config correctly lives in the `workflows`/`workflow_steps` tables) — harmless but confusing; worth deleting or repurposing.
- Approval steps require *unanimous* sign-off from every eligible user in that role+department, and a rejection doesn't cancel sibling pending approvals at the same step — confirm this matches the intended business process.
- No SLA/timeout/escalation/delegation on stalled approval steps, and no segregation-of-duties guard preventing a gatepass creator from also approving their own request.
- `is_admin` on `users` is set via a checkbox in the UI but never read by any authorization check — dead weight that could mislead an admin into thinking it does something.
- No semantic version anywhere in the codebase (`composer.json` has no `version` field, no `CHANGELOG.md`, nothing surfaced in the admin UI).
- `X-Forwarded-For` is trusted by `RateLimitMiddleware` with no trusted-proxy allowlist — spoofable if you're not actually behind a proxy that sets it honestly.
- `GateScanController` renders `Gatepass::scan` and `Gatepass::scan_result` views that don't exist in `src/Modules/Gatepass/Views/` — the gate-scan feature will throw "View not found" until those are built.

---

# Update 5: Tenant migration runner

`database/migrate.php` only ever touched `glee_master` — applying an
incremental migration (005, 006, ...) to every tenant meant running
`mysql ... < database/00X_*.sql` by hand, once per tenant, with no
record of which tenants had already gotten it.

## Added

- `database/migrate_tenants.php` — iterates every tenant in
  `glee_master.tenants` and applies whichever incremental migrations
  it hasn't seen yet, tracked in a new `schema_migrations` table
  inside each tenant's own database (so it's safe to run repeatedly —
  already-applied migrations are skipped, not re-run).
- Runs against a hardcoded allowlist (`INCREMENTAL_MIGRATIONS` at the
  top of the file) — deliberately NOT a glob over `database/*.sql`.
  That directory also holds `002_seed_reference.sql` (data, not
  schema) and `003_truncate.sql`/`004_drop.sql` (destructive by
  name) — a glob would eventually run one of those against every
  live tenant database in a single command. New migrations must be
  added to the allowlist by hand.
- `--dry-run` to preview what would run without changing anything,
  `--tenant=<code>` to target a single tenant, `--include-inactive`
  to also check disabled tenants. Continues past a tenant that fails
  or can't connect rather than aborting the whole run, and exits
  non-zero with a summary if anything failed.

## Usage

```
php database/migrate_tenants.php --dry-run
php database/migrate_tenants.php
```

---

# Update 6: Explicit per-step approver assignment + department-scope fix

## The problem this fixes

A gatepass approved at step 1 (Department Head) advanced to step 2
(Security Approval) and then went silent — the assigned Security
Manager saw nothing under "My Approvals". Root cause: step 2 had no
`department_id` of its own, and the old logic silently required the
approver to be in the SAME department as the request. The actual
Security Manager (George Okoth) is in the Security department, not
the requester's department (IT) — so zero eligible approvers were
ever found, the workflow silently stalled (`NoEligibleApproverException`,
logged as `gatepass.workflow_stalled`), and no approval row was ever
created for anyone to act on. The same problem would have hit step 3
(General Manager Approval) too — nobody currently holds the General
Manager role at all, and even if they did, a GM can legitimately sit
in Finance, Executive Office, or anywhere else, not the department
that happened to file the request.

## ⚠️ New manual steps

1. Run the new migration on every tenant DB:
   ```
   php database/migrate_tenants.php --dry-run    # preview first
   php database/migrate_tenants.php
   ```
2. **Glee only** — run the one-off fixup to unstick the already-stalled
   gatepass and re-point the two Security-role steps to explicit
   assignment:
   ```
   mysql -u youruser -p glee_tenant_db < database/fixups/glee_unstick_gatepass_2.sql
   ```
   This file is deliberately NOT part of the migration chain — it
   references specific row IDs from Glee's own data and isn't safe to
   run against any other tenant.
3. Once a real General Manager user exists, tag them as the approver
   for the "General Manager Approval" step via Settings → Workflows →
   Steps → Assign Approvers (or assign them the General Manager role
   if you'd rather keep that step on dynamic role+department matching
   — see the new options below).

## Added

- **`workflow_steps.assignment_type`** — `role_department` (today's
  dynamic role+department matching, kept as the default for every
  existing step) or **`explicit`** — eligible approvers become an
  admin-picked list of specific users, entirely department-agnostic.
  New table `workflow_step_approvers` holds the tags.
- **`workflow_steps.department_scope`** (only meaningful for
  `role_department` steps) — `same_as_request` (the ORIGINAL fallback
  behavior, now explicit instead of an implicit code path — safe
  default, changes nothing for existing steps), `fixed` (use the
  step's own configured department, e.g. a central desk that clears
  every department's requests), or `any` (role match only, no
  department filter — this is the option that was missing and caused
  the stall).
- **`workflow_steps.approval_rule`** — `all` (unanimous, every
  eligible approver must act — today's only behavior, kept as the
  default) or `any` (first eligible approver resolves the step; the
  rest are marked `skipped` instead of sitting pending forever).
  `gatepass_approvals.status` gained a `skipped` value for this.
- Rejecting a gatepass now also marks any other still-pending
  approvals on that instance as `skipped` — previously they were left
  stranded as "pending" in another approver's queue with no
  explanation after the request was already dead.
- New admin screens under **Settings → Workflows → \[Workflow\] →
  Steps**: **Edit** (change a step's order/name/role label/routing
  mode/rule) and, for `explicit`-type steps, **Assign Approvers** — a
  checklist of every active user (with their department and roles
  shown for context) to tag as eligible approvers for that step. Both
  gated behind the `settings.update` permission (the workflow module
  previously had no permission gating at all on any of its routes;
  this pass only adds it to the new endpoints, existing ones are
  unchanged).
- `database/007_explicit_approvers.sql` — schema-only migration,
  added to `migrate_tenants.php`'s allowlist. Changes no tenant's
  existing data; every current step keeps its exact current real-world
  behavior under the new explicit columns.

## Recommended step configuration (this workflow, going forward)

| Step | Recommended mode |
|---|---|
| Department Head Approval | `role_department` / `same_as_request` (unchanged) |
| Security Approval | `explicit` — tag George Okoth (done by the fixup script) |
| General Manager Approval | `explicit` — tag your real GM once that user exists |

---

## Update 6.1: migrate_tenants.php now runs the full chain (001 → latest)

`migrate_tenants.php` originally excluded `001_schema.sql`, reasoning
it was a no-op against existing tables anyway. Added it back as the
first entry in `INCREMENTAL_MIGRATIONS` so the same one command
handles a tenant in *any* state — brand new (001 creates every
table) or years old (001 no-ops, since `CREATE TABLE IF NOT EXISTS`
does nothing to a table that already exists — it does NOT
retroactively add new columns) and falls straight through to
005/006/007, which retrofit existing tables via `ALTER ... ADD
COLUMN`. Confirmed this reuses the exact same statement-splitting
approach `TenantService::provisionTenant()` already runs live against
`001_schema.sql` for new tenants — no new parsing logic, just reuse
of an already-proven path. 002 (seed data)/003 (truncate)/004 (drop)
remain excluded — see the safety note at the top of the file.

---

## Update 7: Gate scanning parked, not built

Decided to hold off on gate scanning for now — check-in/check-out via
the Gatepass list page (`GatepassController::checkIn`/`checkOut`,
already fully working: buttons, CSRF, policy checks) cover current
needs. The `/gatepasses/scan` routes are commented out in
`routes/web.php` rather than removed, since they'd otherwise error —
`GateScanController` renders `Gatepass::scan`/`Gatepass::scan_result`
views that don't exist. `GateScanController.php` itself is left in
place; re-enabling later just means uncommenting the two routes (and
`$scanLimit`, currently unused for the same reason) once the views
are built. No dead links found anywhere in the nav/dashboard pointing
at the old route, so nothing else needed cleanup.

---

# Update 8: Stall notifications, segregation of duties, permission gating, per-tenant theming

## Segregation of duties

`ApprovalService::createApprovalsForStep()` now excludes the
gatepass's own `created_by` user from the eligible-approver list,
under BOTH assignment modes (`role_department` and `explicit`).
Previously nothing stopped a requester who also held the approving
role — or was explicitly tagged as an approver — from signing off on
their own request. Fetches the gatepass's `created_by` once per step
(same query already used for `same_as_request` department scoping)
and adds `AND u.id != ?` to every eligibility query. If excluding the
requester leaves zero eligible approvers, the resulting
`NoEligibleApproverException` message says so explicitly rather than
looking like an unrelated role/department mismatch.

## Stall notifications

Previously the only way to learn a workflow had stalled
(`gatepass.workflow_stalled` audit event) was to notice it on the
Approvals page — and only if you had `settings.update`. Added
`ApprovalService::notifyStall()`, called from `advanceToNextStep()`'s
existing `NoEligibleApproverException` catch block: emails everyone
holding `settings.update` (same audience as `getStalledInstances()`'s
visibility) with the gatepass number, which step it stalled at, and
why. Best-effort — wrapped in its own try/catch so a mail failure
(bad SMTP config, etc.) can never break the approval transaction that
triggered it, same principle used elsewhere in the app (e.g. the
contact-form notification). Uses the existing `App\Core\Mailer`
(`config/mail.php` driver — `log` writes to `mail_log` only, `smtp`
actually sends).

## Permission gating — gatepass-types, departments, workflows

These three modules were auth-only — any logged-in user, any role,
could create workflows, reassign which workflow a gatepass type uses,
or edit departments. The `settings.update` permission already exists
and already gates other admin config screens (company info,
gatepass-numbering, badge-numbering) — it just was never applied
here. Swapped `$auth` for the existing `$settingsUpdate` middleware
set on every route in these three modules. No new permission module
needed, no reseed required.

## Per-tenant UI theming

New **Settings → Theme** page: header background, sidebar background,
sidebar text, and a single "theme color" that drives buttons, links,
active nav state, and focus rings throughout the app (already fully
token-driven via `public_html/assets/css/token.css` — this just makes
those tokens tenant-configurable instead of fixed).

- Stored in the existing generic `tenant_settings` key-value table
  (`TenantSettingService`, key `'theme'`) — no new table needed.
- New `App\Core\Helpers\ColorHelper` — dependency-free hex math
  (darken/lighten/rgba) so a tenant only picks ONE theme color and
  every derived shade (hover, ring, light tint) is computed
  automatically, instead of asking an admin to pick five related
  colors by hand.
- New `theme_css_vars()` helper (`bootstrap/helpers.php`), called
  from `resources/views/layouts/app.php` right after `app.css` loads
  — renders a `<style>` block overriding just the relevant `:root`
  custom properties with this tenant's saved values (falls back to
  today's exact defaults for anyone who hasn't customized anything —
  zero visual change out of the box).
- **Security**: every color value is validated against a strict
  6-digit hex pattern (`/^#[0-9A-Fa-f]{6}$/`) BOTH on save
  (`ThemeSettingController::update()`, rejects with 422 otherwise)
  AND again on render (`theme_css_vars()`, silently falls back to the
  default rather than trust stored data blindly). This is real
  injection surface — arbitrary tenant-controlled content interpolated
  directly into a `<style>` block on every authenticated page, with no
  HTML-escaping applicable to CSS — so validation is the actual
  defense here, applied twice deliberately (defense in depth: a
  direct DB edit or a future bug in the save path still can't produce
  unsafe output).
- New `--color-header-bg` token added to `token.css` (defaults to the
  existing `--color-surface`, so no visual change until configured);
  `.navbar` in `layout.css` now reads from it instead of the generic
  surface color, giving the header an independently configurable
  background as asked for.
- Gated behind `settings.update`, same as the rest of Settings.
- Scope note: only the authenticated `app` layout is themed right
  now — the login/public-facing pages use a separate layout and
  aren't wired up yet. Extend `theme_css_vars()` into that layout too
  if full branding consistency (including the login screen) is
  wanted later.

---

# Update 9: is_admin cleanup, X-Forwarded-For trust, site-wide CSP, approval rate limiting, versioning

## Removed dead `is_admin` column usage

`is_admin` was written on user create/update and shown as an "Admin"
checkbox, but never read by any authorization check anywhere —
`Permission::can()`/`Auth::role()` only ever consult `user_roles`.
It gave a false impression that toggling it did something. Stopped
writing to it in `UserManagementController` (create + update),
`TenantService::provisionTenant()`, and `Seeder.php`'s CLI tenant
bootstrap; removed the checkbox from both user forms. The column
itself is left in the schema (`NOT NULL DEFAULT 0`) rather than
dropped — nothing reads it, so it's inert either way, and not
dropping it avoids a migration that touches every tenant DB for zero
behavioral gain.

## X-Forwarded-For trust

`RateLimitMiddleware` previously trusted `X-Forwarded-For` /
`X-Client-IP` unconditionally — either header is fully
client-controlled unless you're actually behind a proxy that sets it
honestly, meaning anyone could spoof another user's IP to exhaust
their rate-limit quota, or rotate a fake one to dodge their own.  Now
only trusts those headers when `REMOTE_ADDR` (the real TCP peer,
unspoofable) matches an entry in a new `config.ini` [security]
`trusted_proxies` key — blank by default, meaning "trust nothing,
always use REMOTE_ADDR." Supports exact IPs and IPv4 CIDR ranges
(`10.0.0.0/8`). Set it once you know your actual hosting/proxy setup.

## Site-wide security headers + baseline CSP

`X-Frame-Options`/`X-Content-Type-Options` were only ever set in
`LoginController` — every other page had neither. Moved to
`bootstrap/app.php`, applied to every request, plus a new
`Referrer-Policy` and a baseline `Content-Security-Policy`.

Honest caveat, not hidden: the CSP allows `'unsafe-inline'` for both
`script-src` and `style-src`, because this codebase uses inline
`<style>`/`<script>` blocks in several places (the tenant theme
override, the sidebar-toggle JS in the app layout, a few admin form
scripts) that would break under a strict policy. What it DOES give
you: everything else is locked to `'self'` plus the specific external
origins actually in use (`fonts.googleapis.com`/`fonts.gstatic.com`
for Google Fonts, `cdnjs.cloudflare.com` for Font Awesome) — a
compromised dependency or an injected `<script src="...">` pointed at
an attacker's domain would be blocked. Removing `'unsafe-inline'`
properly is a real follow-up (move every inline style/script to
external files with nonces) — flagged, not attempted here, to avoid
silently breaking pages that currently depend on it.

## Rate limiting on approve/reject

Previously only login/reset/scan were throttled. Added a new
`approval-action` bucket (`config.ini [security]
approval_max_attempts`/`approval_lockout_seconds`, defaults 30/60s)
applied to the approve/reject POST routes — settings-mutation routes
elsewhere are still unthrottled, worth doing next if wanted.

## Basic versioning

`composer.json` gained a `version` field (starting at `1.1.0`) — one
source of truth for which build is deployed, now that you're running
per-tenant subdomains off one codebase. New `app_version()` helper
reads it; shown in the footer on every page (`v1.1.0`). Also fixed a
pre-existing bug in the footer while touching it: it read
`$_SESSION['user']['tenant_name']`, a key that was never actually set
anywhere (tenant identity lives at `$_SESSION['tenant']['name']` via
`Tenant::name()`) — so the footer always silently fell back to the
generic "GPMS" label instead of showing the real tenant name. Fixed
to use `Tenant::name()` directly.

---

# Update 10: Approver delegation, broader rate limiting, nonce-based CSP, starter test suite

## Approver delegation

New self-service "My Delegate" page (`/settings/delegation`, linked from
the profile page) — any user can name a backup approver for a date
range. `ApprovalService::substituteDelegates()` (called from
`createApprovalsForStep()`, both assignment modes) swaps a delegating
user out for their active delegate when building a step's eligible-
approver list, so a tagged/eligible approver going on leave doesn't
silently stall the workflow the same way an unassigned role used to.
Replaces rather than adds the delegate, so an `all`-rule step's
required signoff count doesn't change. New `user_delegates` table
(migration `008_delegation.sql`, one row per user — saving a new
delegate replaces the old). The requester-exclusion (segregation of
duties, Update 8) is re-applied after substitution, in case a
delegate happens to be the gatepass's own requester.

Bonus fix found while touching the profile page: its update form was
posting to `/settings/profile/update`, a route that never existed
(the real one is `/settings/users/profile`) — every profile edit
attempt has been silently 404ing. Fixed.

## Broader rate limiting

New `admin-mutation` bucket (config.ini `admin_mutation_max_attempts`/
`admin_mutation_lockout_seconds`, defaults 60/60s) applied directly to
the shared `$settingsUpdate`/`$rolesCreate`/`$rolesUpdate`/
`$rolesAssign`/`$usersCreate`/`$usersUpdate` middleware arrays in
`routes/web.php` — since routes reuse these arrays, this is a one-line
change per array rather than touching every individual route
registration.

## Nonce-based CSP (script-src)

`script-src` no longer needs `'unsafe-inline'` — a per-request nonce
is generated in `bootstrap/app.php` (new `csp_nonce()` helper), and
every inline `<script>` block across the app (12 files, including the
sidebar-toggle JS in the main layout) now carries
`nonce="<?= csp_nonce() ?>"`. Only script the server actually put
there can run now; anything injected via XSS won't have a valid nonce
and gets blocked outright.

`style-src` still allows `'unsafe-inline'`, and that's a real,
documented limitation, not an oversight: CSP nonces only work on
`<style>` tags, not on `style="..."` attributes, and 17 view files use
inline style attributes (mostly one-off `style="display:inline"` on
small forms). Removing `'unsafe-inline'` from style-src means
eliminating every one of those in favor of CSS classes first — a
real follow-up, not attempted here to avoid a large, easy-to-get-
wrong sweep across many files in one pass.

## Starter test suite

Added `phpunit/phpunit` (dev dependency), `phpunit.xml`,
`tests/bootstrap.php`, and unit tests for `ColorHelper`,
`GatepassPolicy`, and `ApprovalPolicy` (28 test cases total).

- `tests/bootstrap.php` deliberately does NOT load the full
  `bootstrap/app.php` — that file resolves a tenant database from the
  HTTP Host header and sends real headers, neither of which makes
  sense under a CLI test run with no actual request. It's just
  Composer autoloading + a session.
- The policy tests exploit the fact that `Permission::can()` only
  ever reads `$_SESSION['permissions']`/`$_SESSION['is_super_admin']`
  — never the database — so they drive real policy behavior directly
  through the session array (an in-memory SQLite handle just
  satisfies the constructor's type, no query ever runs against it).
  No mocking framework, no MySQL, no fixtures needed.
- Every `ColorHelperTest` expected value was computed independently
  (via a parallel implementation) before being written as an
  assertion, specifically so a real bug in `ColorHelper` would fail
  the test rather than the test having been written to match
  whatever the code happened to output.

**Important — this suite has NOT been executed.** `packagist.org`
isn't reachable from the sandbox these fixes were written in, so
`composer install` (needed to actually pull PHPUnit) couldn't be run
here. Run `composer install && composer test` (or
`vendor/bin/phpunit`) yourself before trusting these pass — the logic
was checked carefully against the actual source by hand, but "checked
by hand" and "verified by execution" are not the same thing, and
that gap should stay visible rather than being glossed over.

---

# Update 10.1: Hotfix — bootstrap/app.php was breaking the entire site

`bootstrap/app.php` had a `?>` inside a `//` line comment (documenting
the nonce attribute syntax used elsewhere). PHP has a specific gotcha
here: a `?>` inside a single-line comment doesn't just end the
comment, it exits PHP mode entirely, immediately. Since that comment
sat partway through the file with no further `<?php` tag afterward,
everything from that point on — the rest of the security-header setup,
session config, and the entire rest of bootstrap (routing included) —
was emitted as raw literal text instead of being executed. This broke
every page on the live site the moment it was deployed.

Fixed by removing the literal `?>` from the comment (described in
words instead). Swept the entire codebase for the same pattern (any
`//` or `#` comment containing a literal `?>`) — this was the only
occurrence anywhere.

**If you deployed the previous zip and the site is currently broken,
this file is the fix — redeploy `bootstrap/app.php` (or the whole
zip) immediately.**

---

# Update 11: Mobile responsive overhaul

Went through every stylesheet looking for concrete, verifiable bugs
rather than just adding generic "make it smaller" media queries.
Found several real ones — this wasn't just under-polished, some of it
was actively broken.

## The core bugs

- **`.table-card { overflow: hidden }`** — used by 13 of the app's
  table views. On any screen too narrow for the table's natural width
  (i.e. most phones, since `.table th` forces no-wrap headers), this
  silently CLIPPED columns off the edge instead of letting you scroll
  to them — data was there, just invisible. This is almost certainly
  what "tables are not loading well" was describing. Changed to
  `overflow-x: auto` with `-webkit-overflow-scrolling: touch`; still
  clips to the card's rounded corners vertically, just scrolls
  horizontally instead of hiding content.
- **`.form-control`/`.form-select` at 14px font-size** — under the
  16px threshold that stops iOS Safari from zooming the whole page in
  every time an input is focused. Affected every form in the app,
  including the **login page**, which used `font-size: 0.875rem` too
  — the very first interaction any mobile user has with the app.
  Fixed on both (16px on the login page always; 16px via the mobile
  media query in the main app, keeping the tighter desktop density
  where iOS zoom isn't a factor).
- **Login page inputs were `width: 80%`** — not 100%. Every login/
  password field had a visibly broken gap on its right side, on any
  screen size, worse on narrow ones. Fixed to `width: 100%`.
- **Sidebar toggle breakpoint mismatch** — the CSS put the sidebar
  off-canvas starting at 992px, but the JS toggle button only treated
  ≤768px as "mobile mode." Between 769–992px, the sidebar was
  positioned off-screen by CSS, but clicking the toggle ran the
  desktop "collapse" branch instead of "open" — there was no way to
  open the sidebar at all in that range. Synced both to 992px.
- **`.form-grid` and `.header-left`/`.header-actions` had zero CSS**
  — used across the Theme, Workflow Steps, Delegation, and other
  recently-added admin pages, but never defined anywhere, so those
  forms/headers rendered as unstyled stacked blocks. Defined
  `.form-grid` as a responsive CSS grid (`auto-fit, minmax(240px,
  1fr)` — collapses to one column on narrow screens without needing
  a matching media query) and gave `.card-header` a real flex layout.

## Mobile drawer polish

The sidebar previously had no backdrop and no tap-outside-to-close —
the only way to close it once opened was tapping the toggle button
again. Added a backdrop overlay (shown/hidden via JS, tap to close),
auto-close when a sidebar link is tapped, body scroll lock while open,
and recovery if the window is resized/rotated past the breakpoint
while the drawer is open.

## Breakpoint coverage

Expanded the mobile (≤768px) and small-phone (≤480px) tiers with
what was actually missing: the navbar's decorative tenant-name center
section is now hidden below 768px (freeing space for the page title,
which is actually useful context); card headers stack vertically
once title+actions get tight; tap targets bumped to a comfortable
minimum height; form action buttons go full-width and stacked below
480px for easier tapping; the navbar user's name text (not the
profile icon) hides below 480px to make room for the toggle + logout
button.

## Not attempted here

Table content itself isn't restructured for mobile (e.g. a
card-based/stacked alternative to a wide table) — the horizontal-
scroll fix makes tables actually usable and is a much smaller, safer
change than redesigning how tabular data displays on narrow screens.
Worth considering as a further step for the widest tables (Gatepass
index, Audit log) if horizontal scrolling still feels cramped in
practice once you've tried it on a real device.

---

# Update 12: Closed the self-approval bypass, added Contractor visit type

## Fix 1: Self-approval bypass (real security fix)

The gatepass create/edit forms had a plain, unguarded "Needs Approval"
checkbox that the server trusted directly. `GatepassService::create()`
set the initial status straight to `APPROVED` and never started a
workflow at all if it was unticked — any user creating their own
gatepass (an employee taking equipment out, exactly the scenario this
system exists to control) could self-approve with one click.

- New `gatepass_types.requires_approval` column (migration
  `009_type_requires_approval.sql`). Configurable only via **Settings
  → Gatepass Types** (admin-only, `settings.update`) — never by
  whoever is creating an individual gatepass.
- `GatepassService::create()`/`update()` now call
  `GatepassRepository::typeRequiresApproval()` and use that
  authoritative value, ignoring whatever the client sends. Defaults
  to `true` (safe) if the type can't be resolved at all.
- `GatepassDTO::fromRequest()` no longer reads `needs_approval` from
  client input at all.
- Removed the now-meaningless checkbox from both gatepass forms;
  replaced with a note that approval is determined by the selected
  type.

### Bonus fix found while touching this: JSON-submitting forms were broken by the earlier global CSRF check

The Gatepass Type create/edit forms submit via `fetch()` with
`Content-Type: application/json`, which means PHP never populates
`$_POST` for them. `Request::input()` only ever read `$_POST`/`$_GET`
— so once the global CSRF middleware (Update 3) started requiring
`csrf_token` on every non-GET request, these two forms were silently
419-rejected on every save, since there was nothing for the check to
find. Root-cause fixed: `Request` now parses a JSON body as a
fallback (cached, so `php://input` is only read once) — a general
fix that also covers any future JSON-submitting endpoint, not a
one-off patch. Added the missing `csrf_token` to both forms' JS
payloads.

## Fix 2: Contractor visit type

New "Contractor" visit type (seeded for both new tenants and existing
ones via `010_contractor_visits.sql`), plus two new fields on
`visits`:

- `contract_reference` — optional PO/contract/work-order reference,
  so a contractor visit can be tied back to an actual engagement
  instead of just a free-text purpose field.
- `escort_required` — flags that this visitor must be accompanied
  on-site. Applies to any visit type, not just Contractor — a
  Business visit occasionally needing an escort isn't blocked from
  recording that.

Added to the visit creation form (visually de-emphasized unless
Contractor is selected, but always usable for any type) and surfaced
in the visits list — an "Escort" badge next to the purpose column for
anyone staffing the gate, plus the contract reference shown inline
when set.

## Deploy

```
php database/migrate_tenants.php --dry-run
php database/migrate_tenants.php
```
Then configure `requires_approval` for each existing gatepass type
under Settings → Gatepass Types — it defaults to `true` for all of
them, but confirm it matches what each type actually needs before
relying on it.

---

# Update 13: Error pages were completely unstyled and hid their own error reason

While investigating a user-reported "404 / Gatepass not found" issue,
found that `resources/views/errors/{403,404,500}.php`:

1. **Loaded no CSS at all** — no `<link>` tag to app.css, errors.css,
   or anything. Every error page has been rendering as bare unstyled
   HTML this whole time.
2. **`errors.css` targeted a `.error-page` wrapper class that was
   never in the actual markup** — so even linking the stylesheet
   wouldn't have helped; the selectors had nothing to match.
3. **Ignored the specific error message entirely.** `Response::abort()`
   always sets `$errorMessage` (e.g. "Gatepass not found.", "You
   don't have permission to view this gatepass.") and makes it
   available to the view, but none of the three templates ever
   printed it — every 404 looked identical regardless of cause, every
   403 the same regardless of *what* was forbidden. This is almost
   certainly why the report was two things at once ("404 Page Not
   Found" and "Gatepass not found") — same event, message just
   wasn't rendered.

Fixed all three: added the CSS link (`errors.css` now self-contained
with its own `@import "token.css"`, same pattern as `login.css`,
since these pages aren't part of app.css's import chain), added the
`.error-page` wrapper, and print `$errorMessage` when it's more
specific than the generic default. Confirmed safe for the 500 page
specifically — `bootstrap/app.php`'s global exception handler only
ever passes the generic string `'Server Error'` to `Response::abort()`
in production (`debug=false`); the raw exception message only ever
reaches the browser in the separate debug-mode branch, which doesn't
go through this view at all.

Also gave `errors.css` basic mobile handling (it had none — same gap
as the rest of the app before Update 11) and a body/reset baseline,
since it can no longer assume `base.css` is loaded.

---

# Update 14: Critical bug — check-in, check-out, and delete always acted on gatepass #1

**Root cause of the "Gatepass not found" reports.** `Router::dispatch()`
always calls route handlers as `$controller->$action($request,
...$routeParams)` — every handler is expected to declare `Request
$request` as its first parameter, with any URL parameters (like
`{id}`) after it. `GatepassController::checkIn()`, `checkOut()`, and
`delete()` were the only three handlers in the entire app that didn't
follow this — they declared only `mixed $id` as their sole parameter.
That meant the `Request` **object** itself was silently bound to
`$id`, and the real ID from the URL (e.g. `2`) was passed as an extra
argument PHP just discards.

`(int) $requestObject` in PHP always evaluates to `1` (object-to-int
casting has no numeric meaning, so PHP returns 1 with a suppressed
warning). That's why every check-in, check-out, and delete — on
*any* gatepass, regardless of which one was actually clicked —
silently operated on and redirected to gatepass **#1**. If #1 didn't
exist (or was a different tenant's/soft-deleted record), you'd get
exactly what was reported: a 302 that "succeeds," landing on a 404
for the wrong gatepass.

This is a pre-existing bug — nothing to do with any change made this
session — confirmed via a full audit of all 41 URL-parameterized
routes in the app; every other controller correctly declares `Request
$request` first. Fixed by adding the missing parameter to all three
methods. No database change needed — this was a pure PHP bug.

**This means check-in, check-out, and delete have likely never
worked correctly for any gatepass other than #1, since this feature
was built.** Worth specifically re-testing all three actions across
several different gatepasses once this is deployed.

---

# Update 15: Visitors create page — 500 error and two more silent bugs

## The reported 500 error

`VisitorRepository::getIdTypes()` queried a table called `id_types` —
that table has never existed; the real one is `identification_types`
(confirmed against `findAll()` in the same file, which already joins
it correctly). Every visit to the visitors create/edit page called
this method to populate the ID-type dropdown, so it threw a SQL
error on every load. Fixed the table name.

## Two more bugs found while fixing it (same file, same page)

1. **`visitors.notes` never existed as a column**, but
   `VisitorRepository::create()`/`update()` already tried to write to
   it, and `VisitorDTO` already carried a `$notes` property end to
   end. This would have thrown its own SQL error the moment anyone
   actually submitted the create form — i.e. immediately after the
   `id_types` fix above. Rather than deleting the half-built code,
   added the column (`011_visitor_notes.sql`) and actually wired a
   Notes field into both the create and edit forms — neither view
   had ever rendered one, so the feature was write-only and
   inaccessible even before it started crashing.

2. **The "Or Add New Company" field did nothing.** Both forms render
   a `new_company_name` text input, but no controller, service, or
   repository code anywhere ever read it — typing a new company name
   and submitting silently kept whatever the dropdown said (usually
   nothing). Added `VisitorRepository::getOrCreateCompany()` (looks
   up by name, creates it if new — safe under concurrent use since
   `visitor_companies.name` is already unique) and wired it into both
   `VisitorController::store()` and `update()`.

## Also fixed: lost audit trail on visitor creation

`VisitorController::store()` already computed `created_by` from the
logged-in user and passed it into the DTO — but `VisitorDTO` had no
property to hold it, and `VisitorRepository::create()`'s INSERT never
included the column at all (it has a `NOT NULL DEFAULT 0`, so this
never errored — it just silently recorded every visitor as created
by user `0`, losing who actually registered each one). Added
`created_by` through the DTO, service, and the actual INSERT.

## Deploy

```
php database/migrate_tenants.php --dry-run
php database/migrate_tenants.php
```

---

# Update 16: DirectAdmin-based tenant provisioning for shared hosting

Shared hosting (this deployment's, DirectAdmin-based) doesn't grant
the app's MySQL user `CREATE DATABASE` — that's locked behind the
hosting panel, not something a regular MySQL connection can do
regardless of credentials. `TenantService::provisionTenant()`
previously assumed it could always run `CREATE DATABASE` directly,
which would fail outright the first time it was actually used here.

## Added

- `App\Core\DirectAdminClient` — thin wrapper around DirectAdmin's
  `CMD_API_DATABASES` HTTP API, authenticated via a Login Key (never
  the real account password). Used for exactly one thing: creating
  (and, on cleanup, deleting) a tenant's database.
- New `[directadmin]` config.ini section: `host`, `port`,
  `username`, `login_key`, `verify_ssl`, `runtime_db_user`. Leaving
  `host` blank keeps the original direct-`CREATE DATABASE` path
  (e.g. for a future VPS deployment where that's available) —
  nothing changes for hosts that don't need this.
- `TenantService::provisionTenant()` branches on whether
  `[directadmin] host` is set: DirectAdmin API for the database-
  creation step when it is, direct SQL when it isn't. Same for the
  cleanup-on-failure path — DROP DATABASE is typically just as
  restricted as CREATE on shared hosting, for the same reason.

## Design choice: one shared runtime DB user, not one per tenant

DirectAdmin's create-database call can grant an *existing* MySQL
user access to a new database rather than forcing a new one per
call. Reusing a single dedicated user (`[directadmin]
runtime_db_user`) across every tenant database means:
- No architecture change to `DB::connect()` — it still uses one
  shared `[database]` credential the way it always has.
- That user only ever accumulates grants on the specific databases
  DirectAdmin adds it to — never server-wide `CREATE`/`DROP` — as
  long as it's a **new, dedicated** user, separate from `[mysql]`/
  `[master_db]`. This happens to be the least-privilege split the
  audit recommended, falling out of the provisioning mechanism
  itself rather than needing separate work.
- DirectAdmin's documented behavior when reusing an existing db user
  is to reset that user's password to whatever is passed in the
  call — always passing the same value already in `[database]
  password]` makes this a no-op, not an unexpected credential
  rotation.

## Also fixed while touching this file

`config.ini [database] name` (the fallback tenant DB name before any
tenant resolves) was set to the **master** database name — a latent
misconfiguration unrelated to today's change. Left blank instead, so
a broken tenant resolution fails loudly (no database selected) rather
than silently querying the wrong one.

## Setup required (see the config.ini comments for the same list)

1. In DirectAdmin: Account Manager → Login Keys → create a key
   allowing **only** `CMD_API_DATABASES`, IP-restricted to this
   server if possible.
2. Fill in `[directadmin] host/username/login_key/runtime_db_user`
   in config.ini. `runtime_db_user` should be a name you haven't
   used before — it gets created automatically on first use.
3. Provision a test tenant and confirm both the database and its
   first admin user come up correctly before relying on this for a
   real client.

---

# Update 17: Admin host had no guard against ordinary tenant routes

Visiting the regular `/login` (or any other ordinary tenant-facing
route) on the admin host (`gpms.albatechsolutions.co.ke`) fell all
the way through to a real database query with no tenant — and
therefore no database — ever connected, surfacing as a raw
`SQLSTATE[3D000]: No database selected` error instead of a clear
redirect. Root cause: `TenantContext::isResolved()` returns `true`
for the admin host by design (so `/master/*` routes work at all),
which meant the existing "is this host resolved?" guard in
`bootstrap/app.php` never caught this case — the admin host counted
as resolved without ever having a usable database behind it.

Added a dedicated guard right after the public-host check: on the
admin host, any request path that doesn't start with `/master`
redirects to `/master/login` before it ever reaches routing.
Confirmed this doesn't affect the master admin's own logout flow —
it already uses a dedicated `/master/logout`, not the shared
`/logout` a tenant session uses.

Also worth flagging from this round: `app.debug` was found set to
`true` on the live server (it had been set to `false` in an earlier
pass) — likely flipped intentionally while debugging the DirectAdmin/
master-DB issues over the last few rounds. Useful in the moment, but
worth explicitly setting back to `false` before real client use —
it's currently printing full file paths and stack traces to anyone
who triggers a server error.

---

# Update 18: Master admin panel was reusing the tenant app shell entirely

The Tenants list/create pages (`TenantController::index()`/`create()`)
rendered with the same `'app'` layout every tenant page uses —
meaning the master admin saw the full tenant business-app sidebar
(Dashboard, Gatepasses, Visitors, Visits, Approvals, Reports, Roles,
Settings) on what's supposed to be a platform-level "manage your
clients" screen, none of which apply or even function there. The
navbar branding read `config('tenant.name', 'Glee GPMS')` — on the
admin host, no tenant is ever resolved, so this silently fell back to
the static `[tenant] name = "Glee GPMS"` default in config.ini,
showing one specific client's name on a screen meant to manage every
client.

Worse than just cosmetic: the shared navbar's logout button posted to
`/logout` (the tenant logout route) and the profile icon linked to
`/settings/users/profile` (a tenant-only route) — neither exists in
master-admin context. Combined with Update 17's new admin-host guard,
clicking either would have just bounced back to `/master/login`
without actually logging anyone out.

## Added

- `resources/views/layouts/master.php` — dedicated layout for the
  master admin panel. Same asset/CSS setup as the tenant `app`
  layout (so it's visually consistent and already mobile-responsive
  from Update 11), but with its own sidebar/navbar and — importantly
  — does NOT call `theme_css_vars()`, since that renders a specific
  tenant's saved branding colors, which has no meaning when you're
  not looking at any one tenant.
- `resources/views/templates/master-sidebar.php` — platform-level
  nav only (currently just "Tenants" — the only master-admin feature
  that exists). Generic server icon instead of a tenant logo.
- `resources/views/templates/master-navbar.php` — "GPMS Platform
  Admin" branding instead of a tenant name; logout correctly posts
  to `/master/logout`; shows the admin's name as a label (not a
  broken profile link, since no master-admin profile page exists yet).
- `TenantController::index()`/`create()` now render with `'master'`
  instead of `'app'`.

---

# Update 19: First real tenant provisioning attempt failed on the untested link

Registering an actual tenant ("movenpick") failed:
`SQLSTATE[42000] [1044] Access denied for user 'albatech_dbadmin'@'localhost'`
— DirectAdmin successfully created the database (confirmed by the
error being "access denied," not "database doesn't exist"), but the
follow-up direct MySQL connection using the just-granted
`runtime_db_user` credentials failed.

This was the one link in the chain `test-directadmin.php` never
actually exercised — it confirmed the DirectAdmin API layer works
(create + delete), but never tried the real PDO connection
provisioning depends on afterward. Rebuilt the test script to cover
the full chain in one run: API create → immediate PDO connect → (if
that fails) retry after a delay to isolate a grant-propagation timing
issue from an actual credential mismatch → API delete cleanup.

Also added a short retry (3 attempts, 0/1/2s delay) around that same
PDO connection inside `TenantService::provisionTenant()` itself —
cheap insurance against a grant-propagation delay on hosts where a
DirectAdmin-issued grant isn't instantly visible to a new connection,
harmless if that turns out not to be the actual cause here.

The half-created `movenpick` database should already have been
cleaned up automatically — the failure was caught by
`provisionTenant()`'s existing try/catch, which runs the DirectAdmin
delete-database cleanup (a separate API call, unaffected by this
MySQL-level access issue) before re-throwing.

---

# Update 20: Unique DB username per tenant — the actual fix

Confirmed via `test-directadmin.php`: DirectAdmin's `CMD_API_DATABASES`
create action reliably grants a user access to a database only when
BOTH are created together in the same call. Reusing one existing
user across multiple later calls (the Update 16/19 design) does not
reliably apply the grant on this host, even though the API reports
success — proven by a real failed provisioning attempt (`movenpick`)
and reproduced on demand by the diagnostic script (1044 "access
denied to database," not 1045 "wrong password" — the credential
itself was fine, the grant just never took).

Rather than chase DirectAdmin's undocumented `CMD_API_DB_USER_PRIVS`
endpoint (found it, couldn't get confident documentation of its exact
parameters — not repeating the mistake of relying on unverified API
behavior twice), switched to the one pattern already proven reliable:
**a fresh, dedicated MySQL username per tenant, created together with
its database in the same call.**

## What changed

- `TenantService::provisionTenant()` generates a short, fixed-length,
  hash-derived username per tenant (`t_` + 16 hex chars) instead of
  reading a fixed `[directadmin] runtime_db_user`. Hash-derived
  specifically because DirectAdmin prepends the account username to
  whatever's sent here (same as the database name), and MySQL
  usernames have a hard 32-character limit — a tenant `code` can be
  up to 40 characters, which would silently overflow that limit if
  used directly.
- **The password stays the single shared `[database]` password for
  every tenant** — only the username varies. This is deliberately
  NOT a move to full per-tenant secrets (that would need encrypted
  storage for a new secret in `glee_master`, a real architecture
  change with its own new risks — see the earlier discussion). A
  username alone isn't a usable credential without the shared
  password, so `tenants.db_username` carries no secret.
- New `tenants.db_username` column (nullable — NULL for any tenant
  provisioned before this, or via the direct-`CREATE DATABASE` path
  on a host that allows it; those keep using the single shared
  `[database]` username exactly as before, no disruption).
- `bootstrap/app.php`'s per-request tenant resolution now also sets
  `runtime_config.database.username` from the resolved tenant's
  `db_username` when present — `DB::connect()` needed zero changes,
  since it already resolved `database.username` through the same
  runtime-override-then-ini-fallback mechanism `database.name`
  already used.
- `[directadmin] runtime_db_user` in config.ini is no longer read —
  left in place with an updated comment rather than removed, so line
  numbers in the file don't shift for anyone with it already open.

## Deploy

```
php database/migrate.php   # applies the new tenants.db_username column to glee_master
```
Then re-run `test-directadmin.php`'s STEP 2 in spirit by attempting a
real tenant registration — this time it exercises the actual new
per-tenant-user path, not the old shared one.

---

# Update 21: Encrypted per-tenant connection strings (hybrid SaaS — client-hosted databases)

Built the piece needed for a tenant's database to live on infrastructure
the client controls themselves (their own server, their own network),
not just on this shared host — while keeping every other architectural
guarantee already in place (single codebase, complete per-tenant
isolation, no repository/service anywhere needing to know this exists).

## New: `App\Core\ConnectionCrypto`

AES-256-GCM (authenticated encryption — tampering with a stored value
causes decryption to fail loudly, not silently produce garbage
credentials) for a tenant's full connection details (host, port,
database, username, password, SSL settings, optional failover host),
JSON-encoded before encryption rather than a hand-rolled DSN string
(avoids ambiguity from special characters in a password corrupting a
parser).

**Where the key lives, and why it matters**: `storage/keys/tenant_connection.key`
— outside `public_html/` (the actual web root; nothing under `storage/`
is ever served) and, critically, NOT inside `glee_master` alongside the
encrypted values themselves. The entire point of encrypting
`connection_string` is that if `glee_master` ever leaks on its own (a
DB dump, a SQL injection read, a careless backup), the encrypted values
inside it are useless without a key that was never in that database at
all. Storing the key in the same table it protects would defeat the
purpose entirely. This does NOT protect against someone with full
access to the app server itself — nothing does, at that point; it
protects against the database leaking independently of the server.

New `bin/generate-tenant-key.php` — one-time setup, refuses to
overwrite an existing key (since every connection string encrypted
with it would become permanently undecryptable).

## New: `App\Core\TenantConnectionManager`

The actual "Tenant Connection Manager" — resolves a tenant row into a
real PDO connection:
- If `connection_string` is set: decrypt it and connect with those
  exact details (any host, any credentials, optional SSL, optional
  failover host).
- If not (every tenant provisioned so far): falls back to the exact
  same resolution that existed before this — shared `[database]`
  host/port/password + that tenant's own `db_name`/`db_username`. Zero
  change in behavior for any existing tenant.
- Retries the primary host a few times with a short delay (same
  reasoning as Update 20's post-provisioning retry), then tries an
  optional `failover_host` once before giving up — opt-in per tenant,
  skipped entirely for tenants that don't set one.
- Per-request connection cache (keyed by tenant id) — the practical
  ceiling for "connection pooling" in a PHP-FPM request model, where
  there's no long-running process to hold a real pool across requests;
  genuine pooling at scale means a proxy layer (e.g. ProxySQL) in front
  of MySQL, which is infrastructure to run, not application code.
- Logs every connection attempt (success/failure/failover) to
  `storage/logs/tenant_connections.log` — deliberately a flat file
  outside any tenant database, not the app's own `audit_logs` table
  (which lives INSIDE a tenant database — logging a failed connection
  to that tenant's own database there would be circular on exactly the
  requests where it matters most).

## Integration: `DB::connect()` — the only call site that changed

`DB::connect()` now delegates to `TenantConnectionManager::connectionFor()`
whenever `TenantContext::hasTenant()` is true (true on every real
request, both dynamic-domain and legacy static mode). Every repository
and service in the app still just calls `DB::connect()` exactly as
before — none of them needed to change, which was the actual point of
introducing this as a distinct manager rather than scattering
tenant-connection logic through the app.

## New master-admin screen: Settings → Tenants → Connection

Per-tenant "Connection" screen (`/master/tenants/{id}/connection`) —
deliberately **write-only**: existing connection details are never
decrypted and displayed back into the form, even to a super admin.
Submitting always replaces the whole connection string; a separate
"Clear" action reverts a tenant to the default shared connection.
Added a badge on the Tenants list showing which tenants currently have
a custom connection set.

## Schema

Two new nullable columns on `glee_master.tenants`:
- `db_username` (Update 20 — unrelated to this update, already shipped)
- `connection_string` (this update) — `text`, holds the encrypted
  blob. NULL for every tenant that doesn't need it.

## What this does NOT include yet, deliberately

- **Real connection pooling at scale** — needs a proxy layer (ProxySQL
  or similar), which is ops infrastructure to stand up and operate,
  not something addressable in application code.
- **VPN tunnel setup** — if a client's database sits behind a firewall
  with no public MySQL port, reaching it needs an actual network
  tunnel between this server and theirs. That's a networking decision
  specific to that client's IT setup, not something to build
  speculatively.
- **Encrypting `db_username`/the shared `[database]` password itself**
  — deliberately left as-is (shared password model from Update 20) for
  every tenant still on this one server; only tenants with an explicit
  `connection_string` get the full encrypted-credentials treatment,
  since that's the only case where a real secret needs storing at all.

## Deploy

```
php database/migrate.php   # applies the new connection_string column to glee_master
php bin/generate-tenant-key.php   # once — generates storage/keys/tenant_connection.key
```
Then `chmod 600` the key file if the script's own `chmod` call didn't
already take effect on your host's permission model, and confirm
`storage/` is included in your backup process — losing the key makes
every stored connection string permanently undecryptable.

---

# Update 22: "Database hosted separately" option on tenant registration

Previously, registering a tenant always created a database on this
server via DirectAdmin, even if the actual intent was to point that
tenant at infrastructure the client (or you, testing locally) already
set up — meaning testing the hybrid-SaaS connection override meant
registering normally first, then separately overriding the connection
afterward, leaving an unused throwaway database behind on webway.host
every time.

## New: `TenantService::provisionHostedSeparately()`

New checkbox on the New Tenant form — "Database hosted separately (not
on this server)". When checked, provisioning takes a completely
different path from the normal one (split out as
`provisionOnThisServer()`, unchanged from before):

- **Never creates a database or runs any schema/seed SQL.** This app
  has no business running schema-altering SQL against infrastructure
  it doesn't own — that's expected to already be done by hand
  (`database/001_schema.sql` through `011_visitor_notes.sql`, in
  order, exactly as documented for local testing) before you register
  the tenant.
- Connects directly to whatever host/port/database/username/password
  is entered, with a clear, specific error if that fails — including
  a dedicated check for the single most likely mistake (registering
  before running the migrations): if the database is reachable but
  has no `users` table, it says exactly that instead of failing
  cryptically a few steps later on the admin-user insert.
- Still creates the first admin user automatically, same as the
  normal path — the client gets a working login without an extra
  manual step, as long as the schema was already there.
- Stores the connection details via `ConnectionCrypto::encrypt()`
  straight into `connection_string` at creation time (previously this
  was only settable after the fact, via the separate Connection
  screen from Update 21).
- On failure, there's deliberately no cleanup/rollback of the external
  database — this app never created it, so it's not this app's place
  to touch it further. Only the registration record is at risk, and
  that simply never gets written if anything fails.

`TenantRepository::create()` now accepts `connection_string` directly
(previously only settable via the separate `updateConnectionString()`
call from the Connection screen).

## Workflow this enables

```
1. Set up the tenant's database wherever it'll actually live
   (your laptop for testing, or a real client's server) —
   run 001 through 011 against it by hand, in order.
2. Register the tenant through the portal, check "hosted
   separately", fill in the connection details directly.
3. Done — no throwaway database left on this server, no
   separate after-the-fact override step needed.
```

---

# Update 23: Test Connection button

Both places you enter a tenant's database connection details — the
Connection screen (Update 21) and the New Tenant "hosted separately"
section (Update 22) — previously required actually saving/submitting
before finding out whether the details even worked. Added a shared
"Test Connection" button to both, using one endpoint and one JS
function rather than building this twice.

## New: `TenantConnectionManager::testConnection()`

Deliberately separate from `connectionFor()` (the method real traffic
uses) — a single attempt with a short 6-second timeout, no retries, no
failover, no per-request caching. An admin clicking "Test Connection"
wants a fast, honest answer immediately, not the multi-second
retry sequence real traffic gets. Checks both that the connection
succeeds AND that the schema looks set up (same `SHOW TABLES LIKE
'users'` check `provisionHostedSeparately()` already used) — reports
three distinct states: can't connect at all, connects but no schema
yet, or connects with schema ready.

Nothing is saved by this action — it takes the same raw connection
details shape the forms already collect and reports what it finds,
full stop.

## New shared endpoint + JS

`POST /master/tenants/test-connection` (gated the same as everything
else in this controller) — takes host/port/database/username/
password/ssl settings directly, never a tenant id. The JS
(`testConnection(prefix)`, added once to the shared `master` layout
rather than duplicated per-view) reads whichever fields exist with a
given id prefix — `ct_` on the Connection screen, `nt_` on the New
Tenant form — so the exact same function serves both forms without
knowing which page it's running on.

---

# Update 24: Module-by-module audit — critical Gatepass regression from manual file upload, plus a real missing feature

## CRITICAL: GatepassController.php was overwritten with GatepassTypeController's content

Root cause of "gatepass module not loading." `src/Modules/Gatepass/Controllers/GatepassController.php`
had been replaced — file path correct, but its actual contents were
`GatepassTypeController`'s entire class (wrong namespace
`App\Modules\Settings\Controllers`, wrong class name, none of
`index()`/`create()`/`store()`/`show()`/`edit()`/`update()`/`delete()`/
`checkIn()`/`checkOut()` existed anymore). Every route pointing at the
real GatepassController fatally errored. This happened during a manual
"upload individual files" step — almost certainly a copy/paste or
upload-destination mix-up between two similarly-named controllers.

Rebuilt the file from scratch, cross-referenced against:
- `routes/web.php`'s actual method list for this controller (9 methods)
- `GatepassService`/`GatepassPolicy`'s real method signatures
- Every view under `Views/` for what data keys each one expects
- Every fix already established this session (Request as the first
  parameter on every handler, object-level policy checks on
  show/edit/update/delete, soft-delete-aware `find()`)

Also found the same upload had **reverted `GatepassTypeController.php`'s
`getPayload()`** back to its old, already-fixed-once bug (an
independent, separate raw `php://input` read that can silently come
back empty). Re-applied that fix.

**Ran a project-wide sweep for the same failure class** (filename vs.
declared-class mismatches) across all 137 non-view PHP files — the two
other hits were false positives (a docblock comment, and a legitimate
second exception class in the same file as `ApprovalService`). The
corruption was isolated to the one file.

## Found and fixed: Reports export routes existed with no implementation

`routes/web.php` already registered all four report export routes
(`/reports/{gatepasses,visitors,visits,audit-logs}/export`), but
`ReportController` never actually had `exportGatepasses()`/
`exportVisitors()`/`exportVisits()`/`exportAuditLogs()` — clicking
Export on any report page would 500. Built all four as CSV downloads,
reusing each report's own filter params (so exporting after filtering
exports the filtered set), with `per_page` raised high enough to
export every matching row rather than one paginated page. Column
headers are taken from the first row's own keys rather than
hardcoded, so they always match whatever the underlying report query
returns.

## Also confirmed clean, project-wide

- Every route's target class+method actually exists (was specifically
  how the export gap was found).
- Zero Request-param-first violations remain anywhere (44+ parameterized
  routes checked).
- No broken `require`/`include` paths anywhere.
- A genuinely well-built, already-existing shared search/filter/sort/
  pagination system (`BaseRepository` + `QueryBuilder` + `Paginator`)
  is already used consistently across all four Reports repositories,
  and a working reusable search partial
  (`resources/views/components/global-search.php`) is already used by
  Gatepass/Visitors/Visits list views. Good existing foundation for
  extending "reusable search/filter" further.

---

# Update 25: New — configurable Gatepass Rules; diagnostics added for the still-unresolved type-saving report

## New: Settings -> Gatepass Rules

Turned the previously-hardcoded eligibility logic in
`GatepassWorkflow::eligibility()` into a real, tenant-configurable
settings page — Systems can now change which gatepass statuses allow
Check-In/Check-Out without needing a code change.

- `GatepassWorkflow::eligibility()` now takes a `$rules` array
  (`checkout_statuses`, `checkin_statuses`,
  `checkin_requires_returnable`) instead of hardcoded status lists,
  merged against `GatepassWorkflow::DEFAULT_RULES` for anything not
  configured — a tenant who's only touched one side still gets a safe
  default for the other.
- Stored via the existing `tenant_settings` key-value mechanism
  (`TenantSettingService`) under `gatepass_workflow_rules` — no new
  table needed, consistent with how numbering formats/theme are
  already stored.
- `GatepassService` fetches this once per instance (not once per
  gatepass in a list) and passes it to every `eligibility()` call.
- **Deliberately NOT configurable**: the underlying sequencing rule
  itself (check-in can never be offered before check-out has actually
  happened) stays enforced in `GatepassWorkflow` regardless of what's
  selected on this page — this page controls WHICH statuses count,
  not whether the ordering protection applies at all. That's the
  guard from Update 24, kept intact on purpose.
- The update handler refuses to save an entirely empty check-out list
  — that would silently block check-out for every gatepass on the
  tenant, not just look like an unusual configuration.

## Cleanup: duplicate route registration

`/settings/gatepass-types/update` (no ID) and
`/settings/gatepass-types/{id}/update` were both registered pointing
at the same controller method. The id-less one was never actually
called by anything — removed it.

## Still open: gatepass_types.allowed_actions reported as not saving

Traced every layer of this path (repository SQL, service, DTO
encode/decode, edit-form rendering, JS submission, routing) and found
each one individually correct and consistent with the others — no
code-level bug found on this pass. Added temporary diagnostic logging
to `GatepassTypeController::update()` (clearly marked, safe to remove
once resolved) that logs the raw request body received, the parsed
checkin/checkout values, and what's actually in the database
immediately after the save. The next real save attempt against this
deployed version will show definitively whether the request is
arriving correctly and whether the write is taking — needed to close
this out with evidence rather than further static-analysis guessing.

---

# Update 26: Check-in failing with "modified concurrently" — a second, independent hardcoded rule found in the repository

## Root cause

`GatepassRepository::checkIn()` had its own hardcoded status
whitelist baked directly into the UPDATE's WHERE clause
(`status_id IN (checked_out, approved)`) — a second, completely
separate copy of "which statuses are acceptable," independent from
`GatepassWorkflow`'s eligibility rules and unknown to Update 25's new
configurable-rules feature. Once eligibility became configurable, the
service could correctly say "this status is fine" while the
repository's rigid, stale hardcoded list disagreed — exactly the
"Check-in failed. Gatepass may have been modified concurrently."
report. `checkOut()` had the opposite problem: no status check
at all, meaning no real concurrency protection whatsoever, and
asymmetric with `checkIn()`.

## Fix

Both methods now do genuine optimistic concurrency control instead of
re-deciding business rules: confirm the row is still in the *exact*
status the service already read and validated as eligible moments
earlier, rather than checking against a fixed list of "acceptable"
statuses baked into SQL. Which statuses are acceptable in the first
place is decided once, by the eligibility check (Settings -> Gatepass
Rules) — the repository's only remaining job is detecting whether the
row changed underneath it between read and write, which is what this
kind of check is actually for.

`GatepassRepository::checkIn()`/`checkOut()` signatures changed
accordingly (`$expectedCurrentStatusId` replaces the old
`$checkedOutStatusId`/`$approvedStatusId` whitelist params);
`GatepassService::checkIn()`/`checkOut()` updated to pass
`$gatepass['status_id']` from the row already fetched at the top of
each method.

---

# Update 27: App-wide sweep — inline event handlers silently blocked by CSP (the likely real cause of the original "types not saving" report)

## What this actually was

While adding the requested live rule-summary to Gatepass Types, found
that both its forms used inline `onsubmit=`/`onclick=` attributes —
which this app's CSP has been silently blocking since it was tightened
earlier this session (nonces authorize `<script>` tags, not inline
event attributes). This is very likely the real explanation for the
original "type not saving" report: the save button's JS handler may
never have fired at all, no error shown, because the browser silently
ignored the attribute rather than throwing anything visible.

**Swept the entire codebase for the same pattern** rather than
assuming it was isolated — found it in 13 files total. Some instances
were more than just "doesn't work": several were
`onsubmit="return confirm(...)"` on delete/reset forms, meaning those
destructive actions may have been submitting with **zero confirmation
dialog** — the browser never got the chance to show it before the
form submitted normally.

## Fixed

- **Gatepass Types create/edit**, **Visits create** (contractor
  fields), **Workflow step_edit/steps** (routing fields), **Tenant
  create** (hosted-separately checkbox + test connection — this had
  reverted from an earlier fix in this same session, same as
  `GatepassTypeController` did; re-applied), **Tenant connection**
  (test connection button), **Badges issue-badge** (auto-generate),
  **Gatepass create/edit** (add/remove item rows).
- **New shared mechanism, added to both `app` and `master` layouts**:
  a `data-confirm="message"` attribute plus one delegated `submit`
  listener — replaces every inline confirm-before-submit app-wide
  with one CSP-safe handler. Applied to: theme reset, delegation
  removal, department deletion, tenant connection revert, gatepass
  deletion.
- **Gatepass item rows specifically needed event delegation, not
  just re-binding** — `removeItem()` was attached both to
  server-rendered rows AND baked into the JS template string used by
  `addItem()` to create new rows dynamically; an inline attribute
  injected via `innerHTML` is blocked by CSP exactly the same as one
  written directly into a view. One delegated listener on the item
  wrapper now covers both existing and future rows.
- **`resources/views/components/modal.php`** — found `closeModal()`
  called by this component didn't exist ANYWHERE in the codebase at
  all; this component's close button has never worked under any
  circumstances, CSP or not (currently unused anywhere in the app,
  fixed for correctness ahead of future use). Rebuilt with a
  `data-modal-target` attribute and an actual, real `closeModal()`
  implementation added to both shared layouts.

## New: live rule summary on Gatepass Types (the originally requested feature)

Create/edit forms now show a live-updating summary — combining the
type's own Check-in/Check-out checkboxes with the tenant's currently
configured Gatepass Rules — explaining in plain language exactly when
each action will actually be available, updating as the checkboxes
are toggled. Directly addresses the "these settings interact
invisibly" gap flagged in the previous discussion.

## Verified clean, project-wide

- Zero remaining inline `onclick=`/`onsubmit=`/`onchange=`/`onload=`
  attributes anywhere in `src/` or `resources/` (confirmed by a fresh
  sweep after all fixes — the only remaining text matches are inside
  this changelog-style explanatory comments, not live markup).
- Full balance/tag-structure check across every touched file.
- Full route-handler audit (117 routes) — every target method exists,
  zero Request-parameter violations.

---

# Update 28: Inbound gatepass types — a real second direction, not a config tweak

## What this adds

Until now, every gatepass assumed one direction: something leaves
first (Check-Out), optionally comes back later (Check-In) — never
the reverse. That's genuinely correct for equipment loan-outs and
goods dispatch, but doesn't fit a contractor arriving with their own
tools, or a visitor bringing a personal laptop on-site — cases where
something arrives first and leaves again later.

Added a `direction` field to Gatepass Types: **Outbound** (existing
behavior, default, zero change for every type already configured) or
**Inbound** (new) — Check-In means "has arrived" (eligible once
Approved), Check-Out means "has left again" (eligible once
Checked-In).

## Deliberately NOT run through the same configurable engine as outbound

Settings -> Gatepass Rules (Update 25) controls outbound eligibility
because that flow genuinely varies enough to be worth tenants
adjusting. Inbound's shape doesn't vary the same way — it's always
"arrive, then leave" — so its sequencing is fixed directly in
`GatepassWorkflow::inboundEligibility()`, not configurable. Two
reasons: it keeps the newly-stabilized outbound rules engine
completely untouched (no risk of destabilizing what was just fixed),
and it means there's no way to misconfigure inbound into the same
before-it-ever-happened bug outbound had before Update 24 fixed it.

## Also fixed while wiring this through

Found a second, independent call site to `GatepassWorkflow::eligibility()`
in `GatepassTypeService::resolveActions()` — currently unused
anywhere in the app, but was calling `eligibility()` with neither
`direction` nor the tenant's configured Gatepass Rules, meaning if it
were ever actually called it would have silently ignored both this
feature and Update 25 entirely. Fixed for correctness rather than
left broken for whenever it's used.

## UI

- Create/Edit Gatepass Type: new Direction dropdown with a plain-
  language example for each option. The live rule summary (Update
  27) is now direction-aware — shows the fixed inbound explanation
  or the existing configurable-outbound explanation depending on
  what's selected, live, without saving first.
- Gatepass Types list: new Direction column (Inbound/Outbound badge)
  for at-a-glance visibility.

## Deploy

```
php database/migrate_tenants.php --dry-run
php database/migrate_tenants.php
```
Adds `gatepass_types.direction` (defaults `outbound`) to every
tenant database — purely additive, no existing type's behavior
changes until you deliberately create or edit one as Inbound.

## Recommended type set (see chat for full reasoning)

| Type | Direction | Returnable | Example |
|---|---|---|---|
| Equipment Loan-Out | Outbound | Required | Company laptop taken off-site |
| Goods / Stock Dispatch | Outbound | No | Stock leaving permanently |
| Visitor / Contractor Item | Inbound | N/A (fixed flow) | Contractor's own tools, personal laptop |
| Item Sent for External Repair | Outbound | Required | Equipment sent off-site, expected back |

---

# Update 29: Phase 1 of simplifying Types/Workflows/Rules — the live summary now shows the real approval chain

## What changed

The Gatepass Type create/edit screens previously showed a selected
workflow as just a name in a dropdown — nothing about what it
actually does. Understanding "what happens for this type" meant
leaving the screen entirely and checking Settings -> Workflows
separately, then mentally combining that with whatever Gatepass
Rules says. That's the core of the "these three things feel like
they overlap" problem discussed in chat — they don't actually
overlap in logic, but nothing showed their combined effect in one
place.

The live summary panel (Update 27) now shows, live, as you pick a
workflow or toggle Requires Approval:
- The real approval chain — actual step order, who approves each
  step (role + department scope, or actual approver names for
  explicit-assignment steps), and whether it's an all-or-any rule.
- A clear warning if Requires Approval is on but no workflow is
  selected (nothing to route to), or if a selected workflow has no
  steps configured yet, or if an explicit step has no approvers
  assigned — all real misconfiguration states that were previously
  invisible until someone actually tried to submit a gatepass.
- Moved the summary panel itself to after the Workflow/Requires
  Approval fields, so it reads as a genuine "here's everything that
  happens" recap of the whole form, not something sitting above
  fields it hadn't accounted for yet.

## Implementation notes

`GatepassTypeController::getWorkflowStepsMap()` fetches every active
workflow's steps in two queries total (not N+1) — reusing the exact
same data shape `WorkflowController::steps()` already uses, so the
summary reflects the real thing, not a re-derived approximation.
Embedded once per page load as JSON, looked up client-side as the
workflow dropdown changes — no additional request needed.

## Still ahead (Phase 2, not built — a genuine feature, not a quick follow-up)

A "Quick Approval Setup" directly on the Type screen for the common
1-2-step case, generating a simple Workflow automatically behind the
scenes — so most tenants never need to visit the Workflows screen at
all. The full Workflow Builder stays available as an escape hatch for
department-scoped/any-vs-all cases. Discussed but deliberately not
started without confirming scope first, given the size of the change.

---

# Update 30: AJAX-enhanced check-in/check-out — direct response to external UX review

An external architecture/security review flagged the app as fully
page-reload-based for every action, specifically calling out
check-in/check-out on the gatepasses list as the sharpest example
(a guard processing many gatepasses in a row genuinely needs
sub-second feedback, not a full round-trip per click). Verified this
directly against the code before acting on it — confirmed accurate,
both actions were plain `<form method="POST">` submits.

**Note on the review itself**: cross-checked its other claims too.
One appears inaccurate — it mentions "a legacy `.env` stub kept for
backward compatibility," but no `.env` file exists anywhere in this
codebase; nothing to clean up there. Everything else checked out
against what's actually in the repo.

## What changed

- `Request::wantsJson()` — new helper, true when the client's
  `Accept` header asks for JSON (which `fetch()` sends automatically).
  Lets one route serve both a plain form POST (full-page redirect,
  works with JS disabled) and an AJAX-enhanced response from the same
  handler, no second endpoint needed.
- `GatepassController::checkIn()`/`checkOut()` — when the request
  wants JSON, returns the gatepass's fresh status and recomputed
  `can_checkin`/`can_checkout` instead of redirecting. The plain-POST
  fallback path is completely unchanged.
- Gatepasses list (`index.php`): check-in/check-out are now
  intercepted client-side — on success, the row's status badge and
  action buttons update in place, with a toast notification (reusing
  the existing flash-message style), no page reload. On failure, an
  inline error toast, the button re-enables, nothing about the list
  is lost (scroll position, other in-progress state).
- Both the check-in and check-out forms are now always rendered
  (visibility toggled via inline style) rather than conditionally
  included — needed so a fresh check-out button can appear
  immediately after a successful check-in without JS having to
  construct new form markup from scratch.

## Deliberately scoped to one view for now

This is the single highest-traffic "many rows, quick repeated
actions" screen, and a contained, self-verifying change. The same
pattern (a `wantsJson()`-aware controller action + a small
`gp-ajax-action` JS hook) is now established and ready to extend to
Approve/Reject and the single-gatepass detail page's own
check-in/check-out buttons, whenever that's next up.

---

# Update 31: AJAX-enhanced Approve/Reject — second surface in the AJAX rollout

Same `wantsJson()` pattern from Update 30, applied to the second
highest-traffic repeated-action screen. This one condensed more than
just "no reload" — the previous flow was genuinely three pages deep
(list -> Review -> a separate confirmation page -> submit) for both
Approve and Reject.

## What changed

- `ApprovalController::approve()`/`reject()` — both now support a
  `wantsJson()` branch returning JSON instead of a redirect. The
  existing GET confirmation pages and POST-redirect behavior are
  completely unchanged for anyone who lands there directly (e.g. a
  notification email link) — this is purely additive.
- Approvals list: **Approve** is now a single inline click — no
  navigation, no confirmation page, row updates immediately with a
  toast. **Reject** required a reason server-side already, so instead
  of skipping that entirely, clicking it reveals an inline reason
  field in the same row (progressive disclosure, no page leaves) —
  Confirm submits via AJAX, Cancel collapses it back with nothing
  sent.
- Reject-specific: validation errors (e.g. server-side re-check on an
  empty reason) show **inline in that row's form**, not just a toast
  — the user is actively engaged with that specific reject action, so
  the error needs to be exactly where their attention already is.
- When the last pending approval in view is processed, the empty
  state ("No pending approvals") appears automatically instead of
  leaving a bare table with just a header.
- "Review" (linking to the full detail page) is untouched — still
  there for anyone who wants full gatepass context before deciding.

## Now two surfaces using the established pattern

Gatepasses list (Update 30) and Approvals list (this update) both
use the same `wantsJson()` + small JS hook approach. Extending to the
single-gatepass detail page's own check-in/check-out buttons, or any
other repeated-action list, is now a known, repeatable pattern rather
than a new design problem each time.

---

# Update 32: Dashboard redesign — role-based sections, individually collapsible charts

## What changed

Previously every user saw the exact same flat grid of 6 stat cards
plus all 3 charts, regardless of role — a receptionist saw the same
dashboard as an admin.

**Now split into three sections, each gated by permission:**

| Section | Shown when the user can... | Cards |
|---|---|---|
| Gate Activity | `gatepasses.checkin`, `gatepasses.checkout`, or `visits.checkin` | Checked In Today, Checked Out Today, Active Visitors, Total Visitors |
| My Approvals | `approval.approve` | My Pending Approvals (links straight to Approvals if > 0), Stalled Workflows (only if *also* `settings.update`) |
| Organization Overview | `reports.view`, `tenant.update`, or `settings.update` | Total Gatepasses, plus all three charts |

**Gated by permission, deliberately not by role name.** Role names
are tenant-editable (Settings -> Roles), so checking against a
specific string like `"Security Manager"` would silently break the
moment a tenant renamed or restructured their roles. Permission keys
(`module.action`) are the actual authorization primitive this app
already uses everywhere else — this reuses it rather than inventing
a parallel role-detection system.

A user with none of these permissions gets a clear "nothing to show
here yet" message instead of a blank page.

## Collapsible — both levels, both persist

Each of the three role-based SECTIONS, and each individual chart
CARD inside Organization Overview, has its own collapse toggle,
remembered per browser via `localStorage` (so a security guard who
collapses "Organization Overview" every day doesn't have to redo it
every visit).

**Chart.js/hidden-container detail, worth knowing**: Chart.js
computes canvas dimensions at render time, and gets it wrong if a
chart's container is `display:none` at that moment (e.g. the
Overview section starts collapsed from a previous visit, and chart
data is still loading asynchronously when that happens). Fixed by
exposing the rendered chart instances on `window.gpDashboardCharts`
and calling `.resize()` on the relevant one whenever its
section/card is un-collapsed — handles both "was fine, then hidden,
then shown" and "was hidden the whole time it first rendered"
correctly, since `.resize()` re-measures and redraws regardless of
prior state.

## `DashboardService`

Added `stalled_workflows` to `getStats()`, reusing
`ApprovalService::getStalledInstances()` directly rather than
duplicating its query — the same "stalled" definition already shown
on the Approvals page, now also a count on the dashboard for anyone
with visibility into it.
