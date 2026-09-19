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
4. Historical stock cost is preserved on inventory movements; current product cost may change without rewriting history.
5. Daily closing will reconcile actual cash against transaction-derived expected cash.
6. Dari and Pashto render RTL; English renders LTR.
7. Shop timezone defaults to Asia/Kabul.
8. AFN is a product invariant, not a configurable multi-currency choice.
9. Sales tax is intentionally absent from the domain.
10. Product stock is stored in the product base unit with six-decimal quantity precision.
11. Product and unit conversion math uses arbitrary-precision decimal arithmetic, never binary floats.
12. Every inventory quantity change produces an append-only stock movement.
13. Expiry-tracked inventory is batch-bound so later FEFO allocation can use the same ledger.
14. Purchase orders do not change stock.
15. Posted goods receipts are the procurement boundary that changes stock.
16. Receipt discount and purchase expenses are allocated server-side to preserve landed cost by item and base unit.
17. Initial purchase-payment records are evidence only until the supplier ledger and cash-drawer batches connect them to financial ledgers.

## Layers

- Controllers: HTTP orchestration only.
- Form Requests: authorization and input validation.
- Services: business workflows and transaction boundaries.
- Models: persistence relationships and casts.
- Policies and permissions: authorization.
- Audit service: sensitive-action traceability.
- Views: presentation only; no authoritative financial calculation.

## Inventory model

- products.stock_on_hand is a fast cached total in the base unit.
- product_units.conversion_factor expresses how many base units exist in one alternate unit.
- product_batches.stock_on_hand tracks the balance of a lot when batch tracking is used.
- stock_movements is the immutable inventory audit trail.
- Opening stock and procurement receipts are recorded through InventoryService.
- Future sales, returns, damage, expiry and stock counts must call the same inventory service.

## Purchasing model

- purchase_orders are non-stock planning documents.
- purchase_order_items retain ordered and received quantities in the ordered source unit.
- goods_receipts are posted, immutable receiving documents.
- goods_receipt_items snapshot invoice cost, allocations and landed costs.
- goods_receipt_expenses store receipt-level transport/loading/freight/other costs.
- ProportionalAllocator allocates whole AFN minor units deterministically so allocations reconcile exactly.
- stock movements reference the goods-receipt item and preserve landed base-unit cost.
- product purchase_cost is updated to the latest received base-unit landed cost for operational pricing, while historical movements remain unchanged.
- purchase_payments record initial payment evidence; supplier balance ledgers are introduced later.

## Concurrency

Inventory writes use database transactions and row locks on affected product and batch rows. Purchase-order receiving locks the order and affected order items, and idempotency keys prevent retried goods receipts or stock movements from duplicating inventory effects. Human document numbers use locked date-scoped sequences.

## Current foundation

Batch 1 established authentication, RBAC, localization, AFN conventions, shop settings, terminals, cashier shifts, audit logging, and modern admin/POS shells.

Batch 2 established categories, brands, units, products, multiple barcodes, unit conversions, batch/expiry foundations, exact quantity math, opening stock, and the append-only stock movement ledger.

Batch 3 establishes suppliers, purchase orders, partial/full goods receiving, receipt expenses, deterministic landed-cost allocation, batch/expiry intake, initial purchase-payment evidence, and stock posting through the shared inventory ledger.
