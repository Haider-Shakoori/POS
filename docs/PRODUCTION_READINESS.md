# Production Readiness — Batch 14

Status: **IN PROGRESS**

## Release gates

- [ ] Full SQLite PHP suite passes.
- [ ] Full MySQL 8.4 PHP suite passes.
- [ ] MySQL `migrate:fresh --seed --force` passes.
- [ ] Frontend production build passes.
- [ ] Laravel config, route and view caches build successfully.
- [ ] Composer dependency audit has no blocking advisories.
- [ ] npm high-severity dependency audit has no blocking advisories.
- [ ] Cross-module golden-path shop day reconciles stock, cash, receivables, payables, profit and daily closing.
- [ ] `composer.lock` is committed.
- [ ] `package-lock.json` is committed.
- [ ] Batch 14 CI is green on the final locked dependency set.

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

## Release decision

Batch 14 is not marked complete until all release gates above pass. Any failed financial reconciliation, migration, production-cache build, dependency audit, or production-database test is a release blocker.
