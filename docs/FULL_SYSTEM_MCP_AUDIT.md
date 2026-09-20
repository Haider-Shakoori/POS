# Full System Audit — MCP/Browser Campaign Report

Date: 2026-09-20
Branch: `audit/full-system-mcp-performance`
Application: Afghanistan supermarket POS (Laravel 13, AFN-only, EN/fa/ps, RTL)

## 1. Scope & Method

- End-to-end audit of every user-facing workflow against the project master specification.
- Two independent execution layers:
  - **PHPUnit feature suite** (`php artisan test`) for business rules, money math, idempotency, permissions, concurrency guards.
  - **Playwright browser harness** driving the live app (built assets) for real DOM/JS/session behavior, RTL, keyboard interaction, and console/network errors.
- Local environment: Windows, PHP 8.5.10, MariaDB 10.4 (XAMPP), Node 22, Chromium (Playwright).
- Harness scripts and screenshots: `C:\Users\Dell.com\AppData\Local\Temp\opencode\pos-audit\`
  (`phase1-foundation.cjs`, `phase4-permissions.cjs`, `phase5-localization.cjs`, `phase8a/8b`, `phase16-*`, `phase17-*`, `phase67-purchasing.cjs`, `phase9*`, `phase10-*`, `out/*.png`).

## 2. Verification Results (browser)

| Area | Result |
|---|---|
| First-run setup wizard on truly-fresh DB (migrate only, zero seed) | PASS — `/` and `/login` redirect to `/setup`; owner created; reference data seeded (6 roles, 11 units, 4 payment methods, 1 terminal); setup locks itself; owner login reaches dashboard; success flash visible |
| Localization (24 screens × en/fa/ps) | PASS — 72/72 checks: correct `dir`, no untranslated keys, no RTL overflow, zero console errors |
| Permission matrix (6 roles, 150 route checks) | PASS — 150/150 |
| POS sale → receipt | PASS — auto-print receipt redirect (`?autoprint=1`), stock decrement, paid totals |
| POS hold → resume → complete | PASS |
| POS returns & voids | PASS — `RET-…` returned 15.00/refunded 15.00; `VOID-…` refunded 15.00; stock restored |
| Purchasing | PASS — category + product create, PO create → approve → receive (landed cost layers), supplier balance updated |
| Customers | PASS — create, credit sale, collection with open shift |
| Expenses | PASS — recorded and listed |
| Keyboard shortcuts | PASS — F2 focus search, F4 customer, F6 hold, F8 held list, F9 pay, F10 confirm+complete, Esc closes; Ctrl+K global palette opens/searches/navigates |
| Global search | PASS — permission-aware results (products/customers/suppliers/sales); users without permissions get none |

## 3. Defects Found & Fixed

1. **500 on goods receipt page** — `GoodsReceiptItem::costLayer()` declared an unimported namespace-relative return type (`App\Models\IlluminateDatabaseEloquentRelationsHasOne`).
   Fix: import + `HasOne` type (`app/Models/GoodsReceiptItem.php`). Regression test: `PurchasingCoreTest::test_goods_receipt_show_page_renders_with_cost_layer_remaining_quantity`.
2. **500 on expenses page** — `OperatingEntryController@index` eager-loaded non-existent `name` columns from `expense_categories` / `payment_methods`.
   Fix: load `name_en,name_fa,name_ps` (`app/Http/Controllers/Cash/OperatingEntryController.php`). Regression test: `CashDrawerTest::test_expenses_index_renders_entries_with_localized_category_and_payment_method`.
3. **500 on customer page** — `CustomerController@show` used `Decimal::` without importing `App\Support\Decimal`.
   Fix: added import. Regression test: `CashDrawerTest::test_customer_show_page_renders_available_credit`.
4. **Silent failures** — pages without local error markup swallowed flashed domain/validation errors.
   Fix: global error banner in `layouts/app.blade.php`; 18 views that render errors themselves opt out via `@section('page-errors', '1')`. Browser-verified single-banner behaviour on both paths.
5. **Shift opening offered occupied terminals** — shift form listed terminals already holding an open shift.
   Fix: `CashDrawerController` filters free terminals. Test: `UiFeedbackTest::test_cash_page_only_lists_terminals_without_open_shifts`.
6. **Missing `ui.name` translation**; raw machine audit event codes shown in the audit log.
   Fix: added key in en/fa/ps; localized `lang/{en,fa,ps}/audit.php` for all 40 events; audit view renders labels with raw code as tooltip. Tests in `RolesPermissionsLanguageTest` + `GlobalSearchTest`.
7. **POS JS race** — `Cannot read properties of undefined (reading 'focus')`.
   Fix: optional chaining on all `$refs.search?.focus()` call sites.
8. **Missing spec shortcuts** — implemented POS F2/F4/F6/F8/F9/F10 and Ctrl+K palette with a new permission-aware `/search` endpoint (`GlobalSearchController`), hint chips in the POS UI. Tests: `GlobalSearchTest` (3).
9. **POS UI enhancement** (requested) — stock-state chips on results, live total on the pay button, clear-cart action, keyboard hint rows, polished empty cart state, tabular numerals. Verified in en and fa/RTL.

## 4. Additions

- **First-run setup wizard** (§76): `app/Support/FirstRunSetup.php`, `app/Http/Controllers/SetupController.php`, `resources/views/setup/owner.blade.php`, routes with throttling, login/home redirects, `setup_*` translations in all locales, `tests/Feature/FirstRunSetupTest.php` (9 tests).
- **Global search endpoint**: `GET /search` (auth + throttle, permission-aware).
- **Regression tests**: `tests/Feature/UiFeedbackTest.php` (4), `tests/Feature/GlobalSearchTest.php` (3), plus additions in `RolesPermissionsLanguageTest`, `PurchasingCoreTest`, `CashDrawerTest`.

## 5. Test Suite Status

- Baseline before campaign: 122 tests / 794 assertions.
- Current: **143 tests / 880 assertions — all passing**.
- Performance suites pass: `LargeDatasetPerformanceTest` (3), `OperationalUiPerformanceTest` (5), `SecurityPerformanceHardeningTest` (4).
- Concurrency/idempotency/rollback coverage present across 13 feature test files.

## 6. Live Performance Baseline (dev, built assets)

| Route | avg |
|---|---|
| /dashboard | 351 ms |
| /pos | 396 ms |
| /reports | 499 ms |
| /cash | 432 ms |
| /sales | 293 ms |
| /inventory/products | 277 ms |
| /purchasing/orders | 247 ms |
| /customers | 252 ms |

No route exceeded 550 ms locally.

## 7. Notes & Residual Items

- Cash-drawer rule (by design): cash payments, cash refunds, collections and cash expenses require an open cashier shift for the acting user; non-cash methods do not. Return/void flows expose this clearly.
- Business-day closing/reopen and shift closing are covered by `DailyClosingReconciliationTest` (not re-driven in the browser to avoid mutating the demo day).
- Working tree contains the campaign changes on `audit/full-system-mcp-performance`; no commit was created (pending explicit request).
- The `pos` database contains demo/verification fixtures (audit users, UI Audit products/POs, returns/voids). Reset with `php artisan migrate:fresh --seed` before demos if desired.
