# Invoice document redesign — brief

Working brief for the invoice PDF/preview redesign. Written from a photo of a
reference document the client supplied; that photo will not be attached to the
session that does the work, so the structure is recorded here instead.

**Scope is the invoice document only** — its preview, its PDF, and the stored
copy. Nothing else changes.

---

## The reference, as structure

The client shared a photo of a sales order produced by another company's
accounting software. Only the **arrangement** is being followed. None of that
company's branding is copied, and the document also carried a real end
customer's name, address and phone number plus a bank account number — none of
which is reproduced anywhere, here or in the template.

Top to bottom, the reference reads:

1. **Company header** — logo top-left, company name in large type, address and
   country beneath it. Document title (`Pesanan Penjualan` in the reference,
   `INVOICE` for us) sits to the right of that block, on its own line.

2. **Two columns under the header.**
   - Left: `Kepada` over the customer's name, address, and PIC with phone.
   - Right: a bordered grid of transaction fields, two per row, each with a
     small caps label above its value — date, document number, payment terms,
     delivery/expedition, delivery date, seller, PO number.

3. **Item table**, full width, ruled. Columns left to right:
   `Kode Barang | Nama Barang | Kts. | @Harga | Diskon | Total Harga`.
   Codes and names left-aligned; quantity, price, discount and total
   right-aligned.

4. **`Terbilang :`** — a single full-width ruled row directly beneath the table,
   holding the grand total spelled out in words.

5. **Two columns below that.**
   - Left: a bordered `Keterangan :` box — free-text notes.
   - Right: the totals stack, each line a label/value pair in its own bordered
     row: subtotal, discount, tax, other charges, then **Total** emphasised
     with a heavier rule.

6. **Approval** — `Disetujui,` on the left with vertical space, then a
   signature rule and `Tgl.` beneath it.

The whole document is monochrome, ruled boxes throughout, no fills or accent
colour. It reads as a printed business form rather than an app screen — that is
the effect to reproduce.

---

## What the client asked for

### Phase 1 — audit before coding
Audit the fields that already exist on `Invoice`, `InvoiceItem`, `Customer`,
`Product`, `CompanyProfile`, and the bank/settings models. Specifically check
for: `notes`, `design_notes`, `terms`, `shipping_cost`, `shipping_type`,
customer address, customer phone/PIC, seller/created-by, product SKU, and the
product name snapshot.

**Do not add a field that already exists.** Report the mapping from existing
field to layout section.

### Phase 2 — design/production notes
`design_notes` is not currently shown on the document. Show it, and keep it
visually distinct from the customer-facing notes:

- **Catatan desain/produksi** — internal, about printing and artwork
- **Catatan untuk pelanggan** — external, addressed to the customer

If `design_notes` is invoice-level, render it as its own block under the item
table. Do not repeat one invoice-level note under every line item. Hide the
section entirely when empty.

### Phase 3 — header
Logo, company name, address and contact on the left; `INVOICE` to the right.
All values come from the company profile — nothing hardcoded.

### Phase 4 — customer block
`Kepada` with name, address, PIC and phone from the customer master. Omit
labels whose data is missing rather than printing empty rows.

### Phase 5 — invoice info block
Right-hand grid: date, invoice number, due date, and — only where the system
already holds them — payment terms, expedition/shipping, and seller/created-by.
**No FOB field**, the system has no such concept. No placeholder data.

### Phase 6 — item table
`Kode Barang | Nama Barang | Qty | @Harga | Diskon | Total Harga`.

Use the SKU/product code snapshot for the code. Show the real product name —
**not** a generated technical description such as `Sablon …`. Any spec worth
showing goes small beneath the name, sparingly. Item calculations are unchanged.

### Phase 7 — amount in words
`Terbilang`: `Rp2.040.000` → `Dua juta empat puluh ribu rupiah`. Use a
helper/service, not a hardcoded string.

### Phase 8 — keterangan
A `Keterangan:` area that may carry the design/production note, the customer
note, and the payment account if the document already shows one. The two note
kinds stay separately labelled — never merged into one unlabelled paragraph.
Bank details come from existing settings, never hardcoded.

### Phase 9 — totals
Bottom-right summary: subtotal, discount, tax, shipping, total, plus any
other-charges line the system already has. Existing calculations are the source
of truth; no new cost logic is introduced to match the reference.

### Phase 10 — approval
`Disetujui,`, a signature rule, and a name/date line. Layout only — no digital
signature and no approval workflow.

### Phase 11 — overall feel
Formal, readable, suited to a printing/manufacturing business, printable, and
not "app-like". Follow the reference's ordering: header → customer + invoice
info → items → terbilang + keterangan → totals → approval. Its colours, logo
and branding are not copied.

### Phase 12 — preview and PDF must match
This has broken before: the preview was right while the downloaded PDF differed.
Preview, downloaded PDF and the stored copy must share one normalised
view-model. No second field-mapping living in another template.

Acceptance: product name, quantity, price, design notes, customer notes,
shipping and total are identical across preview and PDF.

### Phase 13 — backward compatibility
Old invoices must still open. Missing `design_notes`, a customer with no address
or PIC, and empty shipping must all render without error. No destructive
migration for the sake of layout.

### Phase 14 — QA fixture

| | |
| --- | --- |
| Customer | PT Testing |
| Item 1 | Cup PP 12Oz Datar 7GR ASB — qty 3000 @ 480 |
| Item 2 | Tutup Strawless D93 — qty 3000 @ 200 |
| Catatan desain/produksi | Cetak 1 warna putih |
| Catatan pelanggan | Mohon cek kembali spesifikasi pesanan. |

Expect the reference structure, both notes present and not swapped, correct
product names and SKUs, correct subtotal/discount/tax/shipping/total, correct
terbilang, and preview identical to PDF. Run the existing regression suite
afterwards.

---

## Do not touch

FIFO, HPP calculation, stock, payment, piutang, kas & bank, purchase order,
goods receipt, production workflow, reporting business logic.

Only the invoice document's presentation, and the design/production note field.

---

## Head start from the audit

Confirmed present in `app/Models/`:

`Invoice`, `InvoiceItem`, `Customer`, `Product`, `CompanyProfile`,
`BankAccount`, `ApplicationSetting`, `InvoiceItemCostLayer`, `ProductCategory`.

`InvoiceItemCostLayer` is the FIFO layer — off limits.

Templates worth reading first:

- `resources/views/pdf/invoices/`
- `resources/views/invoices/`
- `resources/views/components/invoice-items.blade.php`
- `resources/views/components/invoice-totals.blade.php`

Those two shared components are the obvious place to start on Phase 12: if the
preview and the PDF pull from them differently, or one bypasses them, that is
the divergence to remove.

Repo was on `main`, working tree clean, at the time this brief was written.
