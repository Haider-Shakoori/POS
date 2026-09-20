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
26. Completed sales are reversed by immutable return/void documents; original sale items and commercial totals are never edited.
27. Returned physical stock restores the original sale stock allocations, including original expiry batches.
28. Return COGS restores the exact FIFO cost consumptions used by the original sale.
29. Reversals reduce outstanding customer receivables before creating refund evidence for previously paid value.
30. Held sales are non-financial cart snapshots and never reserve inventory or create receivables/payments.
31. Supplier balances are signed: positive means payable to supplier; negative means supplier credit owed to the shop.
32. Supplier payable history is append-only and goods-receipt commercial totals remain immutable.
33. Purchase returns may remove only stock still traceable to the original receipt cost layer and physical stock/batch.
34. Purchase returns reverse the original item landed value and exact source inventory cost layer.
35. Supplier payments and purchase returns preserve their financial evidence independently; cash-method evidence is mirrored into the drawer ledger by Batch 8.
36. Every drawer cash movement belongs to one cashier shift and terminal and is append-only.
37. Opening float is the first drawer movement for a shift, including zero opening cash.
38. Cash sales and customer collections are inflows; cash purchase/supplier payments, refunds and expenses are outflows.
39. Non-cash payment methods never create drawer movements.
40. Cash-affecting source transactions require an open cashier shift and roll back if their drawer movement cannot be posted.
41. cashier_shifts.expected_cash is derived from the cash movement ledger, never hand-entered.
42. Manual deposits/withdrawals/drawer-to-safe movements are operational cash transfers and do not affect profit.
43. Operating expenses and other income are immutable AFN entries; only cash-method entries affect drawer cash.
44. Batch 9 closes and reconciles shifts against this ledger instead of reconstructing cash from sales, purchases or expenses.

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


## Returns, voids and held sales

- held_sales and held_sale_items store resumable POS cart snapshots only; they do not touch stock, cost layers, payments or customer balances.
- held carts preserve quantities, discounts and customer context, while final checkout still reruns current server pricing, permissions and stock validation.
- sale_returns is the immutable reversal header for both partial/full returns and full remaining-sale voids.
- sale_return_items calculate their reversible value from the original item's net value after line and allocated sale discounts.
- sale_return_stock_allocations restores quantities through InventoryService against the exact original sale stock allocations and batches.
- inventory_cost_layer_restorations restores the original FIFO layer quantities/costs consumed by the sale. Fallback-cost sales create/reuse a synthetic return layer at the original historical unit cost.
- customer receivable reversal is capped by the sale's current balance_due; the rest of the returned value is paid-value refund.
- sale_refunds is immutable refund evidence by payment method. Cash drawer effects remain deferred to Batch 8.
- sales retain their original subtotal, discounts, net total, COGS and gross profit; only reversal summaries/status and current receivable balance are updated.
- a void reverses every still-returnable item and can follow an earlier partial return without double-restoring quantity or cost.

Batch 6 establishes held carts, partial/full sales returns, controlled voids, historical stock/COGS restoration, receivable reversal and refund evidence.


## Supplier payables and purchase returns

- suppliers.current_balance is the authoritative net supplier position. Positive values are payable; negative values are supplier credit.
- supplier_ledger_entries is append-only. Opening balances and pre-Batch-7 goods receipts/initial purchase payments are backfilled during the Batch 7 migration.
- every new posted goods receipt credits the supplier ledger by its authoritative net_total.
- the existing purchase_payments table remains immutable evidence for payments captured at goods-receipt posting; those payments debit the supplier ledger.
- supplier_payments records later settlements and supplier_payment_allocations applies them to oldest outstanding goods receipts first. Amounts attributable to opening balance may remain unallocated to a receipt.
- goods_receipts keep immutable commercial totals. Only paid_amount, balance_due and returned_total are settlement/reversal summaries.
- purchase_returns and purchase_return_items are immutable reversal documents.
- a purchase-return quantity is capped by the receipt item's unreturned quantity, the remaining original inventory cost layer, and physical stock/batch availability.
- a purchase return deducts physical inventory from the original receipt batch when batch-tracked and decrements the exact inventory cost layer created by that receipt.
- partial return value is derived from the original landed_total; the final remaining return uses the residual amount so all partial returns reconcile exactly to the original item landed value.
- purchase returns debit the supplier ledger. If value already paid exceeds remaining payable, the signed supplier balance becomes negative supplier credit.
- goods-receipt balance_due is the document's direct unpaid balance; supplier current_balance is the authoritative net position across all supplier documents and credits.
- supplier payment and purchase-return cash effects remain evidence-only until Batch 8 creates cash-drawer movements.

Batch 7 establishes supplier payable reconciliation, later supplier payments, signed supplier credit, and traceable purchase returns with exact stock/cost reversal.


## Cash drawer and operating entries

- cash_movements is the authoritative append-only physical drawer ledger.
- every movement is bound to cashier_shift_id and terminal_id and carries a posting-order expected_cash_after snapshot.
- CashMovementService serializes writes with shift row locks, guarantees source/idempotency uniqueness, and recalculates the shift's expected_cash from ledger totals.
- ShiftOpeningService creates at most one open shift per user/terminal, binds retries to the same terminal/opening float, and creates exactly one opening-float movement even when opening cash is zero.
- checkout cash payments mirror SalePayment.applied_amount, not tendered cash, because tendered minus change equals the net drawer inflow.
- customer collections mirror their collection amount once; generated per-sale allocation payments are intentionally not double-counted.
- cash supplier payments and initial purchase payments are drawer outflows.
- cash sale refunds are drawer outflows; non-cash refunds create no drawer movement.
- expense_categories separates expense and income categories while operating_entries stores immutable AFN operating evidence.
- cash expenses reduce expected cash; bank/mobile-wallet/other expenses do not.
- cash other income increases expected cash.
- manual cash_deposit, cash_withdrawal and drawer_to_safe movements require cash.manage and a reason; these are excluded from operating profit semantics.
- the Batch 8 migration backfills opening float and eligible pre-existing cash evidence into historical shifts and updates expected_cash.
- runtime cash evidence may not be posted before the active shift's opened_at timestamp.

Batch 8 establishes the authoritative expected-cash ledger and operating expense/income workflow. Batch 9 adds shift closing, actual cash, variance tolerance, business-day locking and consolidated daily reconciliation.


## Shift and business-day closing

- cashier_shift_closures stores immutable, versioned shift-close snapshots. Reopening a shift never edits a prior closure; the next close increments version.
- shift expected_cash is recomputed from cash_movements under row lock at close. No alternate drawer formula exists.
- variance is actual_cash - expected_cash and its sign is preserved; a reason is mandatory when the absolute variance exceeds shop_settings.cash_variance_tolerance.
- a closed shift rejects new cash movements because CashMovementService requires ShiftStatus::Open.
- every shift is assigned a calendar business_date at opening; BusinessDayService serializes open/closed state for that date.
- business_day_closures stores immutable daily snapshots with revision numbers. Reopen + re-close creates the next version and keeps prior snapshots intact.
- business-day close requires every shift for that date to be closed with actual cash and variance recorded.
- daily close reconciles the sum of shift expected cash to the authoritative cash-movement ledger before persisting a closure.
- daily close also validates variance_total = actual_cash_total - expected_cash_total.
- the consolidated snapshot records sales, discounts, returns, COGS reversal, net COGS, gross/net profit, customer collections, purchases, purchase returns, supplier payments, expenses, other income, cash inflows/outflows, expected cash, actual cash and variance.
- closing a business day blocks new sales, customer collections, supplier payments, operating entries, goods receipts, purchase returns, sale reversals, opening stock and customer/supplier opening-balance postings dated to that day.
- reopening a business day is explicit, permission-controlled, reason-required and audited; shift reopen is separately permission-controlled and can occur only while the business day is open.
- prior closure records are append-only evidence and remain unchanged through reopen/re-close cycles.

Batch 9 establishes shift close, actual cash counting, variance/tolerance handling, daily consolidated close, reopening and closed-day posting locks. Batch 10 proceeds to stock counts, damage, expiry and reordering.


## Inventory controls and reordering

- stock_counts and stock_count_items capture immutable expected/physical/variance snapshots before approval.
- creating a count draft has no stock or cost effect.
- approval is separately permission-controlled and posts only non-zero variance through InventoryService.
- approval rejects a count when current product/batch stock differs from the expected snapshot captured at count time; stale counts must be recounted rather than force-applied.
- positive count variance uses adjustment_in, current product purchase cost, and creates a new inventory cost layer.
- negative count variance uses adjustment_out and consumes FIFO cost layers; expiry-tracked adjustments consume only the selected batch's cost layers.
- inventory_writeoffs and inventory_writeoff_items are immutable posted evidence for damage and expiry losses.
- damage/expiry stock movements reduce physical stock and inventory_cost_adjustment_consumptions records the exact cost-layer depletion.
- expiry write-offs require an already-expired batch and cannot be posted against non-expiry products.
- damage and expiry quantities cannot exceed current product/batch physical stock.
- inventory adjustment cost depletion is idempotent by stock movement. Missing historical layers fall back to the current purchase-cost snapshot rather than leaving physical stock with unaccounted cost.
- count approvals and write-offs require the current business day to be open; Batch 9 closed-day locking therefore applies to inventory control postings.
- expiry monitoring separates already-expired positive-stock batches from batches expiring inside the selected window.
- reorder suggestions are advisory only. A product becomes a suggestion when stock_on_hand <= minimum_stock and a threshold/reorder quantity is configured.
- suggested reorder quantity is max(configured reorder_quantity, minimum_stock - stock_on_hand), calculated with exact decimal arithmetic.
- reorder suggestions never create purchase orders automatically.

Batch 10 establishes physical count control, damaged/expired inventory write-off, cost-layer reconciliation, expiry monitoring and advisory reordering. Batch 11 proceeds to reporting, profit and analytics.


## Reporting and analytics

- reporting is read-only and never mutates business data.
- headline net sales use sale net totals less sale returns posted inside the selected report period.
- headline net COGS uses persisted historical sale COGS less posted return COGS reversals; current product purchase_cost never rewrites historical profit.
- gross profit = net sales - net COGS.
- net profit = gross profit + other operating income - operating expenses.
- product and category performance are net of returned quantity, returned value and reversed historical COGS.
- product/category filters aggregate matching sale items rather than whole mixed-product sale headers.
- customer and supplier report balances read their authoritative signed current balances while period activity respects the selected date range.
- inventory valuation sums remaining FIFO cost layers at four-decimal precision and rounds only the final AFN total.
- low/out-of-stock, expiry and damage reporting reads current authoritative inventory and write-off records.
- daily closing history reads immutable BusinessDayClosure snapshots.
- CSV sales export uses the same validated filter contract as the reporting dashboard.
- reports.view gates reporting access; reports.profit gates profit-sensitive output.
- report queries aggregate in SQL and cap detail lists instead of loading transactional tables wholesale.

Batch 11 establishes operational reporting, historical profit analytics, inventory valuation and exportable filtered sales reporting. Batch 12 proceeds to printing, barcodes, import/export and settings.


## Printing, barcodes, import/export and settings

- sale receipts are read-only projections of immutable sale/payment data and never expose COGS or profit fields.
- receipt paper size is limited to supported thermal widths (57mm and 80mm) and receipt language is independent of the operator UI language.
- barcode labels render CODE128 server-side as self-contained SVG so label printing does not depend on an external CDN or browser library.
- barcode label rendering is limited to printable ASCII values supported by CODE128-B and caps a single print request at 100 labels.
- product CSV export is catalog-only and deliberately excludes stock-on-hand.
- product CSV import is create-only: it never overwrites an existing SKU or barcode and never imports or mutates inventory quantities.
- imported stock must still enter through opening stock, goods receipts, approved counts or other InventoryService-backed movements.
- CSV import validates the complete file before creating products, caps a single file at 1,000 product rows, and writes through ProductService so normal catalog rules and audit records remain active.
- shop settings expose shop identity, UI/receipt locale, thermal receipt size, cash variance tolerance, negative-stock policy and discount approval threshold behind settings.manage.
- settings changes are audited and AFN-only/no-tax product invariants remain non-configurable.

Batch 12 establishes thermal receipt printing, CODE128 label printing, safe catalog CSV import/export and operational shop settings. Batch 13 proceeds to security, performance and concurrency hardening.


## Security, performance and concurrency hardening

- failed authentication attempts are rate-limited by normalized username plus client IP; successful authentication clears the limiter and still regenerates the session identifier.
- web responses add defensive browser headers for MIME sniffing, framing, referrer handling, cross-domain policy, permissions policy, object embedding, base URI and form targets.
- HSTS is emitted only for secure production requests so local HTTP development is not broken.
- session payload encryption is enabled by default while Laravel's existing HttpOnly and SameSite session-cookie protections remain in place.
- high-frequency product lookup remains available to POS terminals but is bounded; heavier CSV import and sales-export endpoints use stricter request throttles.
- ShopSettingsStore is request-scoped and migration-safe. Locale middleware, dashboard and the application layout reuse the same settings model inside a request rather than issuing duplicate reads.
- indexes cover sellable/purchasable product-unit traversal, product-first sale-item analytics and customer/date sales reporting, matching the established POS/report query shapes.
- DocumentNumberService now guarantees that its SELECT ... FOR UPDATE sequence lock always runs inside a database transaction even when number generation is called outside another workflow transaction.
- checkout, goods receipt, customer collection, supplier payment, purchase return, sale return, shift opening/closing and business-day close retry the entire outer transaction up to three times on retryable database deadlocks. Existing idempotency keys and unique constraints remain the duplicate-prevention boundary.
- existing product, batch, inventory-cost-layer, customer, supplier, shift and business-day row locks remain authoritative; Batch 13 does not introduce alternate stock, cash, receivable or payable calculations.

Batch 13 hardens the production boundary without changing AFN-only, no-tax, FIFO/FEFO, ledger or closing semantics. Batch 14 proceeds to final golden-path QA and production readiness.


## Final golden-path QA and production readiness

- release verification runs the complete PHP suite on both SQLite and MySQL 8.4; production-sensitive locking, transaction and SQL behavior is therefore exercised on the production database engine rather than inferred from SQLite alone.
- the final deterministic golden path reconciles purchasing, supplier payable, tracked inventory, sale/CASH/credit settlement, customer collection, operating expense, cashier shift close, business-day close and reporting in one shop day.
- report monetary aggregates are normalized to fixed two-decimal strings after SQL aggregation so the public reporting contract does not vary by database driver.
- composer.lock and package-lock.json are committed and CI installs from those resolved dependency graphs.
- the release pipeline verifies MySQL fresh migration/seeding, frontend production build, Laravel config/route/view cache warm-up, Composer audit and npm high-severity audit.
- the root route uses a controller rather than a Closure so production route caching is part of the verified deployment path.
- Batch 14 release candidate verification passed 107 tests / 689 assertions on SQLite and 107 tests / 689 assertions on MySQL 8.4.
- repository/application readiness does not substitute for deployment operations: production still requires HTTPS, production secrets, APP_DEBUG=false, backups, durable infrastructure and a controlled migration/deployment procedure.

Batch 14 completes the planned development roadmap and establishes the tested release baseline for production deployment.


## Operational administration and presentation

Batch 15 closes the post-roadmap operational surface without changing financial-domain semantics:

- User and role administration is permission-protected; only an owner may assign the owner role, self-deactivation is blocked, and at least one active owner must remain.
- Audit logs have a read-only filtered administration surface backed by the existing append-only audit evidence.
- POS terminals can be created, renamed and activated/deactivated; a terminal with an open cashier shift cannot be deactivated.
- Operating expenses and other income have a dedicated paginated ledger while continuing to post through the existing immutable OperatingEntryService and cash-movement rules.
- The dashboard exposes lightweight operational KPIs and recent activity rather than development-roadmap state. It deliberately avoids building the full analytics report on every request.
- Role/permission relationships are loaded once per authenticated User instance and reused for repeated authorization checks during the same request, reducing navigation/layout query overhead.
- The application shell groups operational, insight and administration navigation consistently across English, Dari and Pashto while preserving RTL behavior.
- Batch 15 validation: 112 tests / 712 assertions on both SQLite and MySQL 8.4, with migration/seed, frontend build, production cache warm-up and dependency audits green.

## Large-dataset lookup boundary

Batch 16 introduces a bounded lookup boundary for high-cardinality operational selectors:

- Report filters resolve only the currently selected entity on initial render and retrieve matching products, categories, customers or suppliers on demand.
- Inventory stock-count and write-off forms retrieve matching product/batch targets on demand instead of serializing the full tracked inventory into Alpine state.
- Lookup responses are throttled, capped, permission-protected and use prefix-oriented indexed search.
- Expiry and reorder monitoring use database filtering/counting and independent pagination; totals no longer require loading full collections into PHP.
- Browser lookups debounce input and abort stale requests so rapid typing does not queue obsolete responses.
- Customer and supplier activity reports use deterministic secondary ordering so tied aggregate totals render consistently across SQLite and MySQL.
- Batch 16 validation: 115 tests / 740 assertions on both SQLite and MySQL 8.4, with migration/seed, frontend build, production cache warm-up and dependency audits green.

