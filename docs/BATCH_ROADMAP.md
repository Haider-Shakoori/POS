# Development Roadmap

1. ✅ Foundation & Architecture
2. ✅ Product & Inventory Core
3. ✅ Purchasing & Goods Receiving
4. ✅ POS Cart & Sales Engine
5. ✅ Payments, Customer Credit & Receivables
6. ✅ Sales Returns, Voids & Held Sales
7. ✅ Supplier Payables & Purchase Returns
8. ✅ Expenses, Cash Drawer & Cash Movements
9. ✅ Shift Opening, Daily Closing & Reconciliation
10. ✅ Stock Counts, Damage, Expiry & Reordering
11. ✅ Reporting, Profit & Analytics
12. ✅ Printing, Barcodes, Import/Export & Settings
13. ✅ Security, Performance & Concurrency Hardening
14. ✅ Final Golden-Path QA & Production Readiness
15. ✅ Operational Completeness, UI/UX & Performance

Each batch is implemented on an isolated branch, lightly verified during development, and subjected to broader end-to-end reconciliation in final QA.

## Batch 15 scope

- Replace stale development/batch messaging with a live operational dashboard.
- Finish user/access management, audit-log viewing, terminal management and a dedicated operating expense/income ledger.
- Reduce repeated authorization queries by reusing loaded role/permission relationships during a request.
- Modernize the responsive application shell, navigation, dashboard and administration surfaces without changing financial-domain rules.
- Validate new surfaces on the same SQLite/MySQL CI matrix before merging. ✅ 112 tests / 712 assertions on each database engine.
16. 🚧 Large Dataset & Runtime Performance

### Batch 16 scope

- Replace report product/category/customer/supplier full-catalog dropdowns with bounded server-side async lookup.
- Replace Inventory Operations embedded product/batch target catalogs with bounded async lookup.
- Move reorder qualification into SQL and paginate reorder, expired-batch and expiring-batch monitoring.
- Add targeted lookup/index support for multilingual product/category search, customer/supplier search, inventory status and expiry/batch lookup.
- Debounce and abort stale browser lookup requests.
- Verify that large catalogs are not serialized into normal report/inventory page HTML.
- Re-run the complete SQLite/MySQL release matrix before merge.
