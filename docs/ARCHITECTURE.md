# Architecture

## Product boundary

This repository is a single-shop supermarket POS for Afghanistan. The core domain deliberately supports only AFN and contains no VAT, GST, sales-tax, exchange-rate, or multi-currency abstractions.

## Technology

- Laravel 13 / PHP 8.3+
- MySQL 8+
- Blade
- Tailwind CSS 4
- Alpine.js
- Vite

## Domain rules

1. Persisted financial values use DECIMAL in MySQL and authoritative calculations run server-side.
2. Stock and cash changes require traceable ledger or movement records.
3. Completed financial transactions will be reversed or voided rather than destructively edited.
4. Historical sale cost will be preserved when the inventory-costing layer is implemented.
5. Daily closing will reconcile actual cash against transaction-derived expected cash.
6. Dari and Pashto render RTL; English renders LTR.
7. Shop timezone defaults to Asia/Kabul.
8. AFN is a product invariant, not a configurable multi-currency choice.
9. Sales tax is intentionally absent from the domain.

## Layers

- Controllers: HTTP orchestration only.
- Services: business workflows and transaction boundaries.
- Models: persistence relationships and casts.
- Policies / permissions: authorization.
- Audit service: sensitive-action traceability.
- Views: presentation only; no authoritative financial calculation.

## Concurrency

Transactional modules will use database transactions, row locks where stock or balances can race, database unique constraints for references/idempotency, and server-side revalidation before commit.

## Current foundation

Batch 1 establishes authentication, RBAC, localization, AFN conventions, shop settings, terminals, cashier shifts, audit logging, and modern admin/POS shells.
