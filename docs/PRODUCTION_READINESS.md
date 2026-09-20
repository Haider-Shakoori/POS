# Production Readiness — Batch 17

Status: **READY**

## Release gates

- [x] Full SQLite PHP suite passes.
- [x] Full MySQL 8.4 PHP suite passes.
- [x] MySQL `migrate:fresh --seed --force` passes.
- [x] Frontend production build passes.
- [x] Laravel config, route and view caches build successfully.
- [x] Composer dependency audit has no blocking advisories.
- [x] npm high-severity dependency audit has no blocking advisories.
- [x] Cross-module golden-path shop day reconciles stock, cash, receivables, payables, profit and daily closing.
- [x] `composer.lock` is committed.
- [x] `package-lock.json` is committed.
- [x] Batch 14 financial/reconciliation baseline remains green.
- [x] Batch 15 operational/admin/UI regression suite is green on SQLite and MySQL 8.4.
- [x] User/access management, audit-log viewing, terminal management, and dedicated operating-entry ledger are exposed through permission-protected UI.
- [x] Repeated per-request role/permission checks reuse loaded authorization relationships.
- [x] Final locked dependency CI is green.
- [x] High-cardinality report and inventory selectors use bounded, throttled server-side lookup rather than full-catalog HTML/Alpine payloads.
- [x] Reorder, expired-batch and expiring-batch operational monitoring is database-filtered and paginated.
- [x] Large-dataset lookup/index migration passes MySQL 8.4 fresh migration/seed.
- [x] Cross-database activity-report ordering is deterministic for tied totals.
- [x] Owner-only role/permission administration is available with protected owner/system-role safeguards.
- [x] Role permission changes are audited and verified to drive the existing authorization checks.
- [x] Language switching is an explicit English / Dari / Pashto dropdown on authenticated and login surfaces with persisted RTL locale behavior.

## Golden path

The release scenario covers an owner opening a cashier shift, receiving stock with a partial cash supplier payment, paying additional supplier payable, selling tracked stock with partial customer credit, collecting part of the receivable in cash, recording a cash operating expense, closing the shift with an exact drawer count, closing the business day, and reconciling reporting.

Expected reconciliation for the deterministic scenario:

- Purchase: AFN 100.00
- Supplier paid: AFN 50.00
- Supplier payable: AFN 50.00
- Sale: AFN 90.00
- COGS: AFN 30.00
- Gross profit: AFN 60.00
- Customer cash applied/collected: AFN 60.00
- Customer receivable: AFN 30.00
- Operating expense: AFN 5.00
- Net profit: AFN 55.00
- Ending inventory: 7 units / AFN 70.00
- Opening drawer: AFN 1,000.00
- Cash inflow excluding opening: AFN 60.00
- Cash outflow: AFN 55.00
- Expected/actual closing cash: AFN 1,005.00
- Variance: AFN 0.00

## Deployment requirements

Production deployment must use HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, a unique generated `APP_KEY`, secure session cookies, durable database/cache/queue configuration, database backups, a supervised queue worker when queued work is enabled, and the Laravel scheduler when scheduled tasks are introduced.

Run migrations with a database backup and maintenance/traffic-control plan appropriate for the shop. Never replace the production database with `migrate:fresh`.

## Verified release candidate

Final Batch 17 CI passed on the committed dependency lockfiles:

- SQLite: 120 tests passed / 779 assertions.
- MySQL 8.4: 120 tests passed / 779 assertions.
- MySQL fresh migration and seeding: passed.
- Frontend production build: passed.
- Laravel config, route and view cache warm-up: passed.
- Composer dependency audit: passed.
- npm high-severity dependency audit: passed.
- Final golden-path reconciliation: passed on both database engines.

## Release decision

**READY at repository/application level.** The codebase has passed the Batch 17 release gates, including the unchanged financial golden path, large-dataset coverage, owner-controlled role/permission administration, and trilingual locale-dropdown regression coverage on both supported test databases. Production deployment still requires the environment and operational controls listed above, including HTTPS, secrets, backups, infrastructure configuration and a controlled migration/deployment procedure.
