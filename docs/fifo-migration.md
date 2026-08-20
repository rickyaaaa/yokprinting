# FIFO Costing Audit and Migration

## Audit baseline

- **Architecture:** Laravel 12, Blade/Alpine UI, Eloquent models, service-layer transaction flows.
- **Stock in:** posted `GoodsReceipt` calls `PostGoodsReceipt`, updates `products.stock`, and writes a `stock_movements` purchase row.
- **Stock out:** invoice draft creation currently deducts tracked products immediately through `RecordInvoiceSaleMovements`.
- **Old HPP:** `SnapshotInvoiceItems` copied `products.average_purchase_cost` into `invoice_items.purchase_cost_snapshot`; `PostGoodsReceipt` maintained that field as a moving weighted average.
- **Historical invoice data:** invoice items already snapshot product name and SKU. Existing HPP is retained as-is.
- **Cancellation/editing:** draft invoice edits reverse stock with an adjustment; invoice cancellation previously changed status only. Goods receipt cancellation is deliberately conservative.

## FIFO design

- `inventory_batches` stores one layer per posted goods-receipt item: purchase date, received quantity, remaining quantity, and actual unit cost.
- `invoice_item_cost_layers` records every batch consumption for each invoice item. These rows are the historical HPP source for new transactions and reports.
- `invoice_items.hpp_total` and `invoice_items.unit_hpp` provide a stable line snapshot and keep reporting simple.
- `products.average_purchase_cost` remains for backward-compatible legacy screens/tests, but it is no longer used to cost tracked sales after FIFO layers are available.
- Sale validation locks the product and FIFO layers, rejects insufficient stock, consumes by `purchase_date ASC, id ASC`, and writes the stock movement and cost layers in the surrounding database transaction.

## Migration policy

- Cutoff: the first deployment of `2026_08_20_000000_create_fifo_inventory_layers`.
- Historical invoices are not reconstructed because the existing schema does not prove reliable per-sale layer sequencing. Their stored `purchase_cost_snapshot` is copied to the explicit HPP fields only.
- Existing positive tracked stock becomes one `opening_balance` layer using `average_purchase_cost`, then `last_purchase_price`, then the legacy `purchase_price` as the available cost basis.
- If an old tracked product has no batch at runtime, the service creates a clearly marked `FIFO-LEGACY-FALLBACK` opening layer from its current stock and legacy cost. This is a compatibility fallback, not a reconstruction of old sales.

## Known risks

- The current business flow consumes stock when a draft invoice is created, so FIFO follows that existing lifecycle until the application introduces a separate confirmation state.
- Manual stock adjustments do not yet carry a dedicated FIFO cost input; they remain a stock-ledger concern and should be given an agreed unit cost before being used in valuation reports.
- Goods-receipt cancellation remains conservative: a receipt that has been followed by another stock mutation should be corrected through the existing controlled adjustment flow.
