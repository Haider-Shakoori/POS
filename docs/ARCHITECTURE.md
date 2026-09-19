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
18. Completed sales recalculate all quantities, prices and discounts on the server; browser totals are display-only.
19. Expiry-tracked physical inventory is depleted FEFO from non-expired, non-blocked batches.
20. Financial COGS is consumed FIFO from immutable inbound inventory cost layers, independently of FEFO physical picking.
21. Completed sales carry authoritative payment status, paid amount and balance due without allowing their commercial totals to be edited.
22. Fully settled walk-in sales require no customer account; any remaining balance requires an active registered customer.
23. Customer receivables are append-only ledger entries with transactionally maintained current balances.
24. Customer credit exposure is enforced under row lock against the configured credit limit unless an explicit override permission is granted.
25. Sale payment evidence is recorded independently of the cash drawer; Batch 8 later maps cash-method evidence into drawer movements without rewriting sales.

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


## Sales and costing model

- sales are immutable completed commercial documents with AFN subtotal, discounts, net total, historical COGS, gross profit and outstanding balance.
- sale_items snapshot product/unit identity, quantity, authoritative sale price, discounts and historical item COGS.
- inventory_cost_layers are created from opening-stock and purchase stock movements and retain remaining FIFO quantities.
- the Batch 4 migration backfills all pre-existing opening/purchase stock movements into cost layers.
- inventory_cost_layer_consumptions permanently records which FIFO layers funded each sale item's COGS.
- sale_item_stock_allocations records physical stock movements and FEFO batches independently of financial FIFO cost layers.
- untracked stock or inventory without a cost-bearing inbound layer falls back to the current purchase cost at sale time and snapshots that fallback permanently.
- no payment cash movement is fabricated in Batch 4; completed sales begin with paid_amount 0 and balance_due equal to net_total.

Batch 4 establishes barcode/SKU/multilingual product lookup, the live unit-aware cart, server-authoritative checkout, sale immutability, transactional stock deduction, FIFO historical COGS and FEFO expiry depletion.


## Payments and customer receivables

- payment_methods contains configurable active AFN settlement methods; Cash, Bank, Mobile Wallet and Other are seeded.
- sale_payments stores immutable applied payment evidence. Cash records distinguish applied amount from tendered amount and change.
- checkout supports split payments; applied amounts never exceed the authoritative server-calculated sale total.
- customer-linked sales post the full sale as a ledger debit and checkout payments as ledger credits, so customer history reconciles even when the sale is immediately paid.
- any remaining checkout balance is customer credit and is blocked when it would exceed the customer's limit unless the actor has the dedicated override permission.
- customer_ledger_entries is append-only and snapshots balance_after for every opening balance, sale, sale payment and collection.
- customer_collections are immutable receipts; their amounts reduce the customer ledger and allocate oldest outstanding sales first.
- customer_collection_allocations preserves exactly which sale balances a collection settled.
- sale settlement retries are bound to the original customer/payment set and cannot duplicate stock, payments or ledger entries.
- payment settlement can update only payment_status, paid_amount, balance_due and settlement_finalized_at on a completed sale.

Batch 5 establishes customer accounts, split payments, cash change, credit sales, receivables, collections and customer-ledger reconciliation. Cash-drawer movements remain intentionally deferred to Batch 8.
