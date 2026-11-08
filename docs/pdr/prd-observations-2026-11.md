# Observations on stage 1 — picker, retenciones, municipios, código, contabilización, impuestos, órdenes de compra

| | |
|---|---|
| Status | Draft v0.1 — specification and split |
| Date | 2026-11-07 |
| Author | Sergio Barbosa |
| Parent | [`prd-mustang.md`](prd-mustang.md) (roadmap); extends [`prd-accounting.md`](prd-accounting.md) (stage 1) |
| Source | `Observaciones Sistema Contable.docx` (client feedback on stage 1, with the reviewer Friederick's inline notes) |
| Scope of this document | Fixes to what exists (priority 1), tax reports (priority 2), purchase orders (priority 3); the client's inventory asks recorded as input for stage 3. Product requirements first, the technical plan and the split at the end |

---

## 1. What it is for

The company that tried stage 1 found that the accounts it created did not appear in the product form, that a client who withholds two taxes on one sale cannot be invoiced correctly, that a tercero without a municipality is useless for the DIAN's información exógena, that quotations hide the product code the company identifies products by, and that the accounting of an invoice is only visible in the libro diario. It also asks for the monthly tax working papers (retenciones, IVA, exógena) and for purchase orders. This document fixes the first group, adds the tax reports and the orders, and hands the inventory asks to stage 3.

Users: the same three roles (§8 of stage 1). The tax reports are the accountant's (VIEW_BOOKS); everything else is the billing user's too.

## 2. In and out of scope

**In scope**

1. Account picker: every postable account the company created is findable; a subcuenta with auxiliares stops receiving movements (Colombian practice) and what pointed at it is moved.
2. Several retenciones on one line (Renta, ReteIVA, ReteICA), on cotizaciones, facturas de venta and facturas de compra, posted one journal line per withholding account, snapshotted per withholding with its base.
3. Municipio required on terceros, from the DANE's DIVIPOLA list (departamento + municipio, 5-digit code), país for foreign terceros, a migration path for the terceros that have none.
4. Código of the product on quotation, invoice and purchase-invoice lines, PDFs and the read-only form.
5. A *Contabilización* tab on every document that posts (factura de venta, factura de compra, recibo de caja, recibo de pago), read-only from the journal.
6. *Corregir* an emitted factura de compra: one action that voids it and reopens an editable copy.
7. Tax reports: retenciones practicadas and sufridas per month with their bases, the IVA report (generado vs descontable), and the exógena working papers per tercero (formats 1001, 1003, 1005, 1006, 1007, 1008, 1009) as CSV/PDF.
8. Órdenes de compra: no accounting effect, convertible to a draft factura de compra (the mirror of cotización → factura).

**Out of scope**

- Inventory (stage 3, `prd-inventory.md`): §4.9 records the asks.
- Auto-filling a tercero from the RUT PDF or from a supplier's electronic invoice XML (Friederick's notes 1 and 2): follow-up with stage 4's UBL work (§9 Q-D).
- An approval workflow on purchase invoices (Friederick's "agregar aprobaciones"): §9 Q-A, replaced by *Corregir*.
- The DIAN exógena XML / prevalidador file, UVT tables by year, and the DIAN concept codes themselves: §9 Q-E; Mustang produces working papers.
- Certificados de retención (per tercero, per year): a later item once the data of §4.2 exists.
- Credit notes as the correction path of a *sales* invoice (stage 4).

## 3. Domain model changes

```
Shared ─┬─ Municipality (reference data, DIVIPOLA: code, name, department_code, department_name)   not company-owned
        └─ LineWithholding (mapped superclass): tax snapshot + base + amount, N per CommercialLine

Tercero  + country_code (ISO 3166-1 alpha-2, default CO), + municipality_code (5 digits, required when CO)
Company  + municipality_code
CommercialLine (quotation_line, sales_invoice_line, purchase_invoice_line)
         + product_code (snapshot), − the embedded withholding_* snapshot (moved to <table>_withholding), keeps withholding_amount (the sum)
Account  (no column change) postable = active ∧ level ∈ {subcuenta, auxiliar} ∧ no active child
PurchaseInvoice + corrected_from_id, + corrected_by_id, + active_number_key
PurchaseOrder ──< PurchaseOrderLine (as a purchase invoice's, no payments)   status: draft → emitted → converted | voided
Ledger   + ExogenaConcept (account prefix → DIAN concept code per format), company-owned settings
```

Invariants of stage 1 hold: every emitted document that posts owns one entry; a void adds the reversing one; nothing edits an emitted document (the *Corregir* action is a void plus a new draft).

## 4. Functional requirements

### 4.1 Account picker and auxiliares (observation 2)

**Root cause.** `Ledger\Infrastructure\Query\DoctrineLedgerCatalog::searchAccounts()` returns at most 20 active postable accounts ordered by code, matching `code LIKE 'q%' OR name LIKE '%q%'`. PUC 4135 has 38 seeded subcuentas (`puc.csv`), all postable, so `41359501`/`41359502` are the 39th and 40th results for "4135" and never reach the list; for a name search ("celulares") they do appear, but the user searched by the parent's code.

**Rules**

- *Search*: `GET /accounts/search` returns up to 50 by default, accepts `?limit=` (≤ 200), answers `total` (how many matched) so the picker can say "Mostrando 50 de 61: escribe más". A query with spaces matches every word in the name (`AND` of `LIKE`s); digits stay a code prefix. Results keep code order.
- *A subcuenta with active auxiliares is not postable.* `Ledger\Application\PostableAccounts` (exists) becomes the one rule: active, level subcuenta or auxiliar, **and no active child** (`AccountRepository::hasActiveChildren(companyId, code)`, index `ledger_account_parent`). `LedgerJournalPoster::post()`, `PostingRule`, the tax, payment-method, product and tercero account checks and the search all use it. Posting to one answers 409 `account_has_auxiliaries` with `detail.account_code`; the message names the account: "La cuenta 413595 tiene auxiliares: los movimientos van a una de ellas."
- *Subdividing an account in use.* When `POST /accounts` adds the **first** child under a postable account, the response says what pointed at the parent (`moved: {posting_rules: [...], taxes: n, payment_methods: n, products: n, terceros: n}`). With `move_references: true` (the default) Ledger re-points its own references (posting rules, taxes' sales/purchase accounts, payment methods) to the new child in the same transaction and publishes `AccountSubdivided {companyId, parentAccountId, childAccountId}`; Catalog (`product.revenue_account_id`, `expense_account_id`) and Party (`tercero.receivable_account_id`, `payable_account_id`) handle it. Every move is audited (`posting_rule.changed`, `tax.updated`, `payment_method.updated`, `product.updated`, `tercero.updated`, with from/to). With `move_references: false` nothing moves and the chart screen shows the parent with a warning badge ("Sin auxiliar por defecto") until the accountant fixes the references by hand. Existing journal lines on the parent stay where they are: the balance de prueba already groups by parent.
- *UI*: the chart tab's "+ Subcuenta" dialog warns before saving the first child of an account in use ("413595 deja de recibir movimientos. Lo que la usa pasará a 41359501: regla *ingreso*, 2 productos…") with the checkbox *Mover a la nueva auxiliar* on. `features/pick-account` shows the "N más" hint and keeps the code-or-name behaviour.

### 4.2 Several retenciones per line (observation 4)

**Fields.** A line's *Impuesto retención* becomes *Retenciones*: zero or more withholding taxes, **at most one per kind** (`retefuente`, `reteiva`, `reteica`; `TaxKind`). The per-line tax dialog (`features/line-taxes`) shows one select per kind (Retefuente, ReteIVA, ReteICA), each "Ninguna" or an active withholding tax of that kind in force on the document's date; the grid cell lists the chosen names ("Rte 4 % · ReteIVA 15 %"). *Aplicar estos impuestos al producto de ahora en adelante* keeps setting the product's single default retención (the Retefuente one if any, else the first chosen); a product default of several retenciones is out of scope.

**Rules**

- Totals: `Retenciones = Σ over lines and their retenciones`, each computed on the line's discounted base (`TaxBase::Subtotal`) or on the line's IVA for ReteIVA (`TaxBase::ChargeTax`), exactly; the document-level sum is rounded once and the cents shared by largest remainder across every (line, retención) pair (`DocumentTotals::shares`), so per-account journal lines and the PDF's per-tax summary both add up to *Retenciones* to the cent. The editor's preview (`widgets/document-editor/model/totals.ts`) mirrors it case for case; `DocumentEditor.test.tsx`/`totals.test.ts` and `DocumentTotalsTest` share the same fixtures.
- Each retención of a line is stored with its own snapshot (tax id, name, kind, calculation, value, account on this side) plus `base_amount` (the line's subtotal, or its IVA for ReteIVA) and `amount` (its share). The line keeps `withholding_amount` = Σ of its retenciones.
- A newly chosen tax must be active and in force on the date (as today, 422 `lines.N.withholdings.K.tax_id`); the same kind twice is 422 `lines.N.withholdings` "Una retención por tipo en cada línea."; a tax the draft already had keeps its snapshot.
- Documents (quotation, sales invoice, purchase invoice) show in the totals panel one row per retención tax: name, base, value. PDFs do the same (`QuotationPdf::taxes`, invoice and purchase PDFs).
- Duplicate, convert (quotation → invoice) and correct (§4.6) copy every retención.

### 4.3 Municipio required (observation 1)

**Fields (tercero).** *País* (ISO code, default Colombia), *Departamento* and *Municipio* (one picker, "Antioquia › Medellín (05001)", searchable by name or code), *Ciudad* (free text, only when País ≠ Colombia). The company profile gets the same País/Municipio.

**Rules**

- DIVIPOLA is seeded once as reference data (`geo_municipality`: 5-digit code, name, 2-digit department code, department name; 33 departamentos including Bogotá D.C., ~1 122 municipios), from `src/Shared/Infrastructure/Geo/divipola.csv` (DANE's official list; the client's "Tabla de Municipios.xls" was not attached, §9 Q-B). The table has no `company_id`: it is public reference data, not an owned entity (recorded in Decisions).
- `POST /terceros`, `PUT /terceros/{id}`, `POST /terceros/quick`: `country_code` (default `CO`); when `CO`, `municipality_code` is required and must exist (422 `municipality_code`: "Elige el municipio."); when not `CO`, `city` is required ("Escribe la ciudad."). On save of a Colombian tercero `city` is filled with the municipality's name (lists, PDFs and the exógena address keep working).
- Existing terceros keep `municipality_code` null until their next save (then required). `GET /terceros?missing_municipality=1` lists them; the terceros list shows a banner "N terceros sin municipio · Ver" while any exist; the exógena screen (§4.7) refuses to export while a listed tercero has none and links to the filter.
- `TerceroOutput` and `TerceroSummaryOutput` add `country_code`, `municipality_code`, `municipality_name`, `department_code`, `department_name` (kept `city`). The Ley 1581 export includes them.
- Friederick's notes (take the data from the RUT / from the invoice): §9 Q-D, follow-up.

### 4.4 Código on documents (observation 3)

- Every line of a cotización, factura de venta and factura de compra snapshots `product_code` when it is written (documents copy what they use; a product renamed or recoded later does not change an emitted document). Lines by account (purchases) leave it empty.
- The PDFs add a *Código* column before *Descripción* (quotation, sales invoice, purchase invoice; the templates are `templates/pdf/quotation/quotation.html.twig`, `sales_invoice/invoice.html.twig`, `purchase_invoice/purchase_invoice.html.twig`). The read-only form shows the code in the product cell (the label is already "código · nombre"). Line outputs add `product_code`.
- Existing lines are backfilled from `product.code` by the migration.

### 4.5 Contabilización tab (observation 5b)

- `GET /ledger/entries?source_type=&source_id=` answers `{items: JournalEntryOutput[]}`: the document's entry and, when voided, its reversal (`reverses_id` set), by number. Permission READ_DOCUMENTS: a document's own postings are part of a record the person may already open; the libro diario stays VIEW_BOOKS (§9 Q-F).
- The pages of factura de venta, factura de compra, recibo de caja and recibo de pago get `Tabs` *Documento* | *Contabilización* (kit `Tabs`/`TabPanel`, as Configuración) once the document is emitted; the tab shows each entry's number, date, description and lines (Cuenta, Nombre, Tercero, Débito, Crédito) with totals, through one widget `widgets/journal-entry-view`. A draft has no tab; a quotation or purchase order never has one.

### 4.6 Corregir a factura de compra (observation 5a)

Documents stay immutable once emitted (CLAUDE.md). What the client needs — fixing a typo in a recorded purchase — is one action instead of two:

- `POST /purchase-invoices/{id}/correct` `{reason}` → 201 `PurchaseInvoiceOutput` of the **new draft**. In one transaction: the emitted invoice is voided exactly as `/void` does (reversing entry dated today, payables voided, number kept, reason "Corregida: <reason>"), and a draft copy is created with the same supplier, supplier number, issue date, due date, notes, lines (every retención), formas de pago and attachments (files copied); the draft records `corrected_from_id`, the voided one `corrected_by_id`. Refused like a void: 409 `document_has_allocations` (void the payment first), `document_not_emitted`, `document_voided`, `period_locked`.
- The supplier number stays unique per supplier among invoices that are **not voided**: the column `active_number_key` holds the number while the invoice is not voided and `NULL` once voided; the unique constraint moves to `(company_id, tercero_id, active_number_key)` (MariaDB allows repeated NULLs), so the database still enforces it and the voided original keeps its number for the record.
- UI: the read-only banner of an emitted purchase invoice says "Una factura emitida no se edita. *Corregir* la anula y abre una copia editable." with the button (WRITE_DOCUMENTS). The list shows a *Corregida* hint on the voided one linking to its replacement.
- Why not approvals (§9 Q-A): see the open question.

### 4.7 Tax reports (observation 7)

A new page *Reportes › Impuestos* (`/reportes/impuestos`), VIEW_BOOKS, with tabs. Every report is a `TabularReport` (Reporting), exports to CSV and PDF with the existing `ReportExportController` conventions, and is built from the documents (line-withholding rows, lines' IVA, invoices, terceros), never by SQL of ours over the ledger's books; the ledger's figures come from `Ledger\Application` (`LedgerMovements`) for the *Según libros* column.

**(a) Retenciones practicadas** (what the company withheld, from facturas de compra): filter *Desde–Hasta* (months, default the current month), *Tipo* (Retefuente | ReteIVA | ReteICA | todas). Rows: tercero (identificación, DV, nombre), tax name and rate, base, retención, documents count; grouped by month then kind; totals per kind and the balance of the kind's accounts (2365/2367/2368) in the same period *Según libros* with the difference. Only emitted, partially paid and paid invoices by issue date; voided ones are excluded and counted in a footnote ("3 facturas anuladas en el periodo no se incluyen"; a void dated in a later month explains a difference with the books).

**(b) Retenciones sufridas** (what clients withheld, from facturas de venta): the mirror (1355xx), per client; the input of a future certificate.

**(c) IVA**: for the period, *IVA generado* by rate (base, IVA) from sales lines whose charge tax kind is `iva`, *IVA descontable* by rate from purchase lines, *ReteIVA practicada* and *sufrida*, *Impoconsumo*, and the net (a pagar / a favor). Periods are month ranges, so bimonthly and cuatrimestral returns are a filter.

**(d) Exógena (working papers)**: filter *Año gravable*, one tab per format; rows per tercero with the DIAN identification columns (tipo de documento code, número, DV, apellidos, nombres, razón social, dirección, código departamento, código municipio, país) followed by the format's values:

| Format | What Mustang computes | What it lacks (typed by the accountant) |
|---|---|---|
| 1001 Pagos o abonos en cuenta y retenciones practicadas | per tercero and **account** (the cost/expense account of the purchase lines): pago deducible, IVA mayor valor del costo (lines whose IVA was posted to the expense: none today), retefuente, reteIVA practicada | the *concepto* code per account (5001 honorarios, 5002 comisiones, 5003 servicios, 5004 arrendamientos, 5007 compras…): a settings table account-prefix → concept (`GET/PUT /ledger/exogena-concepts`), seeded with the obvious ones (5110→5001, 5135→5003, 5120→5004, 6205/1435→5007, 5195→5016) and editable; rows without a concept show blank and the export warns |
| 1003 Retenciones que le practicaron | per client: base and retefuente sufrida (1355 lines) | — |
| 1005 IVA descontable | per supplier: IVA descontable, IVA devuelto (none: no purchase notes yet) | — |
| 1006 IVA generado | per client: IVA generado, impoconsumo | — |
| 1007 Ingresos recibidos | per client and revenue account: ingresos brutos, devoluciones (none yet) | concept code per revenue account (4001 operacionales default) |
| 1008 Cuentas por cobrar at 31/12 | per client: the cartera balance at the date (`CarteraQueries`, as of 31/12) | — |
| 1009 Cuentas por pagar at 31/12 | per supplier: the cartera de proveedores balance at the date | — |

*Cuantías menores*: each format accepts a threshold in pesos (default 0: report everyone); rows under it are grouped into one "Cuantías menores" row with the DIAN pseudo-NIT 222222222. UVT values and the year's resolution thresholds are not stored (§9 Q-E). The export is CSV/PDF, not the DIAN XML; terceros without municipality block the export (§4.3).

### 4.8 Órdenes de compra (observation 6)

- Fields: Proveedor (tercero), Fecha, *Fecha esperada de entrega* (optional), lines as a factura de compra's (product **or** account usable on purchases, descripción, cantidad, valor unitario, % descuento, impuesto cargo, retenciones), Observaciones; no formas de pago. Series `OC` (`SeriesKind::PurchaseOrder`, prefix `OC`, editable in Configuración › Numeración).
- Lifecycle: `draft → emitted → converted | voided`. Emission takes the number and freezes it, posts **nothing**. *Enviar* e-mails the PDF to the supplier (same quota and queue as other documents). *Convertir en factura* (once, from emitted) dispatches `CreatePurchaseInvoice` with the same supplier, lines (taxes copied, product code), dated today, no supplier number and no payments; the invoice records `purchase_order_id`; the order becomes `converted` with `converted_invoice_id`. *Anular* from emitted (number kept), *Duplicar* from any state. Partial receipts and stock movement are stage 3.
- List: `/ordenes-de-compra`, filters as the quotations list; menu entry under Compras.

### 4.9 Inventory asks (observation 8) — input for stage 3, not built here

The client asks for: entries/exits, real-time stock, several warehouses, kardex and traceability, lots and expiry dates, costing by average and PEPS/FIFO, minimum-stock alerts, integration with purchases, sales and accounting. Against the stage-3 outline in `prd-mustang.md`: warehouses, stock per product and warehouse, entries by purchase, exits by sale, adjustments, transfers, kardex, valuation and weighted-average cost of sales are **in** the outline; **beyond it** are FIFO/PEPS (OQ-8 in the roadmap: the outline excludes it; this client wants it), lots and expiry dates (excluded: "lots and serials"), minimum-stock alerts (not mentioned). `prd-inventory.md` must decide them; purchase orders (§4.8) give stage 3 its "expected receipt" document.

## 5. Accounting and posting impact

- **Several retenciones per line.** A.1 (factura de venta): Dr each retención sufrida to its snapshot account, else its kind's concept (`retefuente_sufrida` 135515, `reteiva_sufrida` 135517, `reteica_sufrida` 135518), with the client as tercero, grouped by account (`Sales\Application\Posting\Movements`). A.3 (factura de compra): Cr each retención practicada to its snapshot account, else `retefuente_practicada` / `reteiva_practicada` / `reteica_practicada`, with the supplier as tercero, grouped by account (`PurchaseInvoiceEntry::add`). Because the shares of §4.2 add up to the document's *Retenciones*, Σ débitos = Σ créditos still holds by construction; `SalesInvoicePostingTest` and `PurchaseInvoicePostingTest` gain the two-retenciones cases (Renta 2,5 % + ReteIVA 15 % + ReteICA 0,966 % on one line, and on three lines with odd cents).
- **Subdivided accounts.** The poster refuses an account with active auxiliares (`account_has_auxiliaries`). Moving references keeps posting working after the accountant subdivides.
- **Corregir** posts the reversal of the original (as a void) and nothing for the draft until it is emitted again.
- **Nothing else posts**: purchase orders and the tab are read-only on the books; the tax reports read documents and compare with `LedgerMovements`.
- Money stays `Shared\Domain\Money` on the server and decimal strings on the wire; the editor's preview uses its `Decimal` (BigInt) and never posts anything.

## 6. API changes (`snake_case`; additive unless said)

| Method | Path | Change |
|---|---|---|
| GET | `/accounts/search` | `?limit` (≤ 200, default 50); response adds `total`. Subcuentas with active auxiliares are not returned |
| POST | `/accounts` | input adds `move_references` (default true); response `AccountOutput` adds `moved: {posting_rules: string[], taxes: int, payment_methods: int, products: int, terceros: int} \| null` |
| GET | `/posting-rules`, `/settings/taxes`, `/settings/payment-methods` | outputs add `account_postable: bool` (false when the account was subdivided and not moved) |
| POST/PUT | `/quotations`, `/sales-invoices`, `/purchase-invoices` (+ `/purchase-orders`) | line input adds `withholdings: [{tax_id}]`; `withholding_tax_id` is still accepted and means `withholdings: [{tax_id}]` (deprecated) |
| GET | the same documents | `*LineOutput` adds `product_code` and `withholdings: [{tax_id, name, kind, calculation, rate, account_id, base_amount, amount}]`; `withholding_tax_id/name/rate(/kind)` stay and read the first retención ("Ninguno" / null when none). Document outputs add `withholdings_by_tax: [{tax_id, name, kind, rate, base, amount}]` |
| GET | `/geo/departments`, `/geo/municipalities?q=&department=` | new, every role: `{items: [{code, name}]}` / `{items: [{code, name, department_code, department_name}]}` |
| POST/PUT | `/terceros`, `/terceros/quick`, `/terceros/{id}` | `country_code` (default CO), `municipality_code` (required when CO), `city` (required otherwise). 422 on `municipality_code`, `city`, `country_code` |
| GET | `/terceros` | `?missing_municipality=1`; summary and full outputs add `country_code`, `municipality_code`, `municipality_name`, `department_code`, `department_name` |
| GET/PUT | `/company` | adds `country_code`, `municipality_code` (+ names); PUT requires the municipality when CO |
| GET | `/ledger/entries?source_type=&source_id=` | new, READ_DOCUMENTS: `{items: JournalEntryOutput[]}`; 400 `invalid_source` |
| POST | `/purchase-invoices/{id}/correct` | new: `{reason}` → 201 the new draft. 403; 404; 409 `document_has_allocations`, `document_not_emitted`, `document_voided`, `period_locked`; 422 `reason`. `PurchaseInvoiceOutput` adds `corrected_from_id`, `corrected_by_id`, `purchase_order_id` |
| GET | `/reports/taxes/withholdings/{practiced\|suffered}` | `?from=YYYY-MM&to=YYYY-MM&kind=`; `WithholdingReportOutput {from, to, groups: [{month, kind, rows: [...], total_base, total_amount, ledger_amount, difference}], voided_count}`. VIEW_BOOKS |
| GET | `/reports/taxes/vat` | `?from&to` → `VatReportOutput` (generated by rate, deductible by rate, reteiva, impoconsumo, net). VIEW_BOOKS |
| GET | `/reports/taxes/exogena/{format}` | `format` ∈ 1001,1003,1005,1006,1007,1008,1009; `?year=&threshold=` → `ExogenaOutput {year, format, rows, minor_amounts_row, missing_concepts: int, missing_municipality: int}`. VIEW_BOOKS |
| GET | `/reports/taxes/{withholdings/practiced\|…\|exogena/1001}/export` | `?format=csv\|pdf` and the screen's filters; `?check=1`; 422 `export_too_large`, `exogena_terceros_incomplete` |
| GET/PUT | `/ledger/exogena-concepts` | `{items: [{account_prefix, format, concept_code}]}`; PUT replaces the list. MANAGE_BOOKS, audited (`exogena_concepts.changed`) |
| GET | `/company/numbering` | adds kind `purchase_order` (prefix OC) |
| GET/POST/PUT/DELETE | `/purchase-orders`, `/purchase-orders/{id}` | as `/quotations` with purchase lines (`product_id` xor `account_id`) and `expected_date?`; statuses `draft\|emitted\|converted\|voided` |
| POST | `/purchase-orders/{id}/emit`, `/emit-and-send`, `/send`, `/void`, `/duplicate`, `/convert`; GET `/pdf` | as a quotation's; `convert` → the order with `converted_invoice_id`; 409 `purchase_order_not_open`, `purchase_order_already_converted` |

As built (the items refined the contract): `ExogenaOutput` has `minor_amounts_rows` (one per concept, as the DIAN
groups cuantías menores) and `totals`; `WithholdingReportOutput` has `kind` and `totals`, its rows `calculation`;
`VatReportOutput` has `generated_difference` and `deductible_difference`; `PurchaseInvoiceSummaryOutput` has
`corrected_by_id`; reports refuse bad filters with 400 `invalid_date`, `invalid_period`, `invalid_kind`, `invalid_year`,
`invalid_threshold`; the exógena routes live in their own `ExogenaController`. The README's API reference is the
contract.

Error codes added: `account_has_auxiliaries`, `invalid_source`, `purchase_order_not_open`, `purchase_order_already_converted`, `exogena_terceros_incomplete`; violation messages on `lines.N.withholdings`, `municipality_code`, `city`, `country_code`.

## 7. Migrations (item 0, one migration per concern, dev and test databases)

1. `quotation_line_withholding`, `sales_invoice_line_withholding`, `purchase_invoice_line_withholding` (`id`, `company_id`, `line_id` FK, `position`, the six `TaxSnapshot` columns, `base_amount`, `amount`); **data**: one row per line whose `withholding_tax_id IS NOT NULL` with `amount = withholding_amount`, `base_amount = subtotal_amount` (or `tax_amount` when `withholding_kind = 'reteiva'`); then drop `withholding_tax_id, withholding_name, withholding_kind, withholding_calculation, withholding_value, withholding_account_id` from the three line tables (keep `withholding_amount`). `DbalCatalogUsage::TAX_COLUMNS` lists the new tables.
2. `product_code VARCHAR(40) NULL` on the three line tables, backfilled by join on `product`.
3. `geo_municipality` (reference, no `company_id`) seeded from `divipola.csv` by the migration (multi-row INSERTs, ~1 150 rows).
4. `tercero.country_code CHAR(2) NOT NULL DEFAULT 'CO'`, `tercero.municipality_code CHAR(5) NULL` (+ index); `company.country_code`, `company.municipality_code`.
5. `purchase_invoice.corrected_from_id`, `corrected_by_id`, `purchase_order_id` (nullable uuids), `active_number_key VARCHAR(40) NULL` = `supplier_invoice_number` where status ≠ voided; drop `purchase_invoice_supplier_number`, add unique `(company_id, tercero_id, active_number_key)`.
6. `purchase_order`, `purchase_order_line`, `purchase_order_line_withholding`; one `numbering_series` row (`purchase_order`, `OC`, 1) per existing company; `SeriesKind::PurchaseOrder` and `ProvisionNumberingSeries` for new ones.
7. `ledger_exogena_concept` (`company_id`, `format`, `account_prefix`, `concept_code`), seeded per company with the defaults of §4.7(d) by a `CompanyProvisioner` and the migration for existing companies.

## 8. Acceptance criteria

1. (OBS-1) The accountant adds 41359501 and 41359502 under 413595 (the `ingreso` rule): the response lists what moved, the rule now points at 41359501, the product form's *Cuenta de ingreso* finds "41359502" by code and by "gravados"; emitting an invoice posts revenue to 41359501, never to 413595; posting explicitly to 413595 is refused with `account_has_auxiliaries`.
2. (OBS-2) A sales invoice line of $1.000.000 with IVA 19 %, Retefuente 2,5 % and ReteICA 0,966 % and ReteIVA 15 %: Retenciones = 25.000 + 9.660 + 28.500 = 63.160; Total neto = 1.126.840; the entry has Dr 135515 25.000, Dr 135518 9.660, Dr 135517 28.500; the editor preview showed the same figures before saving; the PDF lists the three retenciones with their bases.
3. (OBS-3) The same with a purchase invoice: Cr 2365xx, 2367xx, 2368xx per retención; the retenciones practicadas report for the month shows the supplier with base 1.000.000 and each retención, and *Según libros* equals the report.
4. (OBS-4) Saving a Colombian tercero without municipio is refused with a violation on `municipality_code`; a foreign one needs *Ciudad*; the terceros list counts those migrated without one and `?missing_municipality=1` lists them.
5. (OBS-5) A quotation PDF prints the *Código* column; converting it carries the codes to the invoice; renaming the product afterwards changes neither.
6. (OBS-6) An emitted purchase invoice shows the *Contabilización* tab with the A.3 entry; after a void the tab also shows the reversal; a billing user sees the tab, a draft has none.
7. (OBS-7) *Corregir* on an emitted, unpaid purchase invoice leaves it `voided` (reason recorded, reversing entry today) and opens a draft with the same supplier number, lines and attachments; emitting the draft does not trip the supplier-number uniqueness; *Corregir* on a paid invoice is refused with `document_has_allocations`.
8. (OBS-8) The IVA report for a bimester equals Σ sales lines' IVA and Σ purchase lines' IVA of emitted documents in it, and its *Según libros* row matches 240805/240810 for the period.
9. (OBS-9) Exógena 1001 for the year lists every supplier with pagos, retefuente and reteIVA by account and concept, groups rows under the threshold into 222222222, and refuses to export while a listed tercero lacks a municipio.
10. (OBS-10) A purchase order is emitted (OC-1), e-mailed, converted into a draft purchase invoice with identical lines and no number; the libro diario shows no entry for the order; a second conversion is refused.
11. (OBS-11) Every new table and endpoint: another company's id answers 404 (`TenantIsolationTest` extended).

## 9. Open questions (each with the default the build follows)

| # | Question | Default | Why |
|---|---|---|---|
| Q-A | Editing an emitted factura de compra: (a) approval step, (b) *Corregir* = void + reopen a copy, (c) both? | **(b)** | The project rule is immutability through voids. (a) adds a status, a permission the matrix lacks and delays posting (IVA descontable would not be in the books until approved); it still does not let an approved invoice be fixed. (b) keeps every number and entry in the audit trail, is one click, and needs no new role. Approvals stay a follow-up if the client confirms they want a reviewer step |
| Q-B | DANE list: the client's .xls is not available | **DIVIPOLA** (DANE's official departamentos + municipios, 5-digit codes) seeded as static reference data; ISO country + free-text city for foreign terceros; existing terceros required on next save, `?missing_municipality=1` as the report | Exógena needs exactly departamento + municipio codes and país |
| Q-C | Scope of inventory | **Out**; §4.9 records the asks for `prd-inventory.md`; purchase orders are **in**, last | Stage 3 deserves its own PRD; orders have no accounting effect and mirror cotización → factura |
| Q-D | Auto-fill a tercero from the RUT PDF or a supplier's UBL XML | **Out**, follow-up with stage 4 | Stage 4 brings the UBL parser; the RUT is a PDF with no stable structure |
| Q-E | Exógena: how far? | **Working papers** (CSV/PDF per format, per tercero, thresholds typed, concept codes from an editable account→concept table seeded with defaults), not the DIAN XML/prevalidador | Mustang lacks UVT tables, yearly resolutions and concept codes per account; the accountant files from the papers |
| Q-F | Who sees the *Contabilización* tab? | **READ_DOCUMENTS** for a document's own entries; the libro diario stays VIEW_BOOKS | The person already sees the document's amounts; the entry adds account codes of their own document |
| Q-G | Several retenciones: any limit? | **One per kind** (retefuente, reteiva, reteica) per line | Colombian practice: one Renta concept, one ReteIVA, one ReteICA per line |
| Q-H | Subdividing an account the posting rules/products/terceros use | **Move references to the new auxiliar** by default (audited), optional off | Otherwise the next emission fails in the user's exact scenario |
| Q-I | Do voided documents count in the tax reports? | **No**; counted in a footnote; *Según libros* shows the books' figure and the difference | A void in a later month is the accountant's adjustment to explain, not to hide |
| Q-J | Corregir a factura de venta too? | **No** | A sales invoice is the company's (soon electronic) document: stage 4's nota crédito |

---

## Technical plan

Started 2026-11-07, base branch `feature/observations-2026-11`, `symfony-react-app` process, parallel items below. Stack unchanged (Symfony 8.1 / Doctrine, React 19, cPanel: no new process, no Redis; the only new static data is two CSVs read by migrations).

### Where it lives

| Backend | Change |
|---|---|
| `Shared` | `DocumentTotals`/`LineInput`/`LineAmounts` take a list of withholding rates and return per-withholding shares; `LineWithholding` mapped superclass; `Geo` (Municipality, `Municipalities` port, `DbalMunicipalities`, `divipola.csv`, `GeoController`) |
| `Ledger` | `PostableAccounts` rule, `AccountRepository::hasActiveChildren`, `DoctrineLedgerCatalog::searchAccounts` (limit, words, total), `AddAccountHandler` + `AccountSubdivided` event, `JournalQueries::bySource`, `ExogenaConcept` settings |
| `Party` | Tercero country/municipality, validation, `?missing_municipality`, `MoveTerceroAccounts` handler |
| `Company` | municipality on the profile, `SeriesKind::PurchaseOrder` |
| `Catalog` | `MoveProductAccounts` handler |
| `Sales` | `QuotationLine`/`SalesInvoiceLine` withholdings collection + `product_code`, `SalesInvoiceContent`, `SalesInvoicePosting`, PDFs |
| `Purchasing` | the same on `PurchaseInvoiceLine`, `PurchaseDraftResolver`, `PurchaseInvoiceEntry`, `CorrectPurchaseInvoiceHandler`, `PurchaseOrder` aggregate |
| `Reporting` | `TaxQueries` (DBAL over `*_line_withholding`, lines, invoices, terceros), `ExogenaQueries`, `TaxTables`, controllers |

| Frontend | Change |
|---|---|
| `features/pick-account` | "N más" hint, `limit` |
| `features/line-taxes` | one select per retención kind |
| `widgets/document-editor` | `DraftLine.withholdings: string[]`, `totals.ts` per-withholding shares, grid cell, totals panel rows, read-only code column |
| `widgets/chart-of-accounts` | subdivide warning dialog |
| `widgets/journal-entry-view` (new) | the entry table; used by four pages |
| `entities/municipality` (new) | `MunicipalityPicker` on the kit's `SearchCombobox` |
| `pages/terceros`, `features/quick-create-tercero`, `widgets/company-settings` | País/Municipio |
| `pages/sales-invoices`, `purchase-invoices`, `cash-receipts`, `supplier-payments` | tabs; *Corregir* on purchases |
| `pages/tax-reports` (new), `pages/purchase-orders` (new), `widgets/purchase-order-list` (new) | |

### Nearest existing features to copy

Quotation (`Sales\Domain\Model\Quotation`, `pages/quotations`) for purchase orders; `DuplicatePurchaseInvoiceHandler` + `VoidPurchaseInvoiceHandler` for *Corregir*; `DbalCarteraQueries` + `CarteraTables` + `ReportExportController` for the tax reports; `TerceroPicker` for the municipality picker; `SettingsPage`'s `Tabs` for the document tabs.

### Verification

Unit: `DocumentTotalsTest` (several withholdings, odd cents, ReteIVA on IVA), `PostableAccountsTest`, `MunicipalityTest`, `PurchaseOrderLifecycleTest`, `ExogenaMinorAmountsTest`. Functional per endpoint (role, each refusal, another company → 404). Acceptance OBS-1 … OBS-11 in `tests/Functional/Acceptance/`. Vitest: `totals.test.ts`, `LineTaxesDialog.test.tsx`, `JournalEntryView.test.tsx`, `MunicipalityPicker.test.tsx`, pages. Smoke specs per item; a manual run for PDFs and e-mails.

### Left out on purpose (README "Known gaps")

- Terceros: no RUT/XML autocomplete (Q-D); terceros created before this release have no municipio until saved again; the company's municipio is required only on its next save.
- Retenciones: one per kind per line; a product's default retención is still one tax; no certificados de retención yet.
- Exógena: working papers only (no XML, no UVT tables, thresholds typed by hand, concept codes from an editable table with defaults); formats 1004, 1010–1012 and payroll formats not produced; 1001's "IVA mayor valor del costo" is always 0 (Mustang posts IVA descontable in full).
- Tax reports exclude voided documents and show the books' figure next to them; no automatic adjustment rows.
- Purchase invoices: *Corregir* only while nothing is allocated (void the payment first); no approvals.
- Purchase orders: no partial receipt, no stock, no "received" state (stage 3); one conversion per order.
- Account picker: an account subdivided with `move_references: false` must be fixed by hand (the badge shows where).
- Inventory asks (§4.9) are not built.

### How the CLAUDE.md rules are kept

Money is `Money`/`Decimal` everywhere, decimal strings on the wire (per-withholding `base_amount`/`amount` included). Every new owned table (`*_line_withholding`, `purchase_order*`, `ledger_exogena_concept`) implements `CompanyOwned` and is loaded by `(company, id)`; `geo_municipality` is reference data shared by every company and is the stated exception (no company column, read-only). Only `JournalPoster` writes `journal_*` (the reports read them through `Ledger\Application`). Emitted documents are never edited: *Corregir* voids and creates. cPanel: no process, no Redis; the seeds are migrations. API `snake_case`, PHP camelCase. Copy through `t()` in `shared/i18n/locales/es/` (`taxReports.json`, `purchaseOrder.json`, `journalEntry.json`, new keys under existing namespaces). Screens from `@/shared/ui` (`Tabs`, `DataTable`, `FilterBar`, `FormModal`, `SearchCombobox`, `MoneyInput`, `DateInput`). YAML in block form; `.ts` generics as `function f<T>()`.

## Contract (item 0)

Built by the coordinator on `feature/observations-2026-11`, alone:

- The seven migrations of §7 (dev and test), the two CSVs (`divipola.csv`, the exógena concept defaults in `ExogenaConceptDefaults`).
- `Shared`: `LineWithholding` mapped superclass and the three entities (`QuotationLineWithholding`, `SalesInvoiceLineWithholding`, `PurchaseInvoiceLineWithholding`, in their contexts) mapped; `CommercialLine` gains `withholdings()` (ordered collection), `productCode()`; `DocumentTotals` with `LineInput::$withholdings: list<TaxRate>` and `LineAmounts::$withholdings: list<Money>` (+ `DocumentTotalsTest` cases); `Shared\Application\Geo\Municipalities` port + `DbalMunicipalities` + `GeoController` (working, it is reference data).
- `PurchaseOrder`, `PurchaseOrderLine`, `PurchaseOrderLineWithholding` entities with constructors and getters; `PurchaseOrderRepository` port + Doctrine adapter; `SeriesKind::PurchaseOrder`, `Numbering::purchaseOrder()`, provisioning.
- `Ledger`: `AccountRepository::hasActiveChildren()` signature, `JournalQueries::bySource()` signature (501 route), `ExogenaConcept` entity + repository, `AccountSubdivided` event with a no-op handler.
- Every Input/Output DTO of §6 with the new fields (line outputs read `withholdings` from the collection already, so existing endpoints keep working), new routes answering 501, `openapi.json` + `api.d.ts` regenerated.
- `DraftLine.withholdings: string[]` in `widgets/document-editor/model/types.ts` with `withholding_tax_id` kept as deprecated alias until item 3 removes it.
- i18n files `taxReports.json`, `purchaseOrder.json`, `journalEntry.json` (empty, commented); CSS files; regression sections 14–22 with their ID ranges; smoke spec files `accountPicker.spec.ts`, `withholdings.spec.ts`, `terceroCity.spec.ts`, `productCode.spec.ts`, `journalTab.spec.ts`, `purchaseCorrect.spec.ts`, `taxReports.spec.ts`, `exogena.spec.ts`, `purchaseOrder.spec.ts`; menu entries *Órdenes de compra* (Compras) and *Impuestos* (Reportes) routed to placeholder pages; README API rows reserved per item.

## Split

| # | Slug | Item | Owns (context / slice, files) | Tests first | Browser cases | Depends on | Model |
|---|---|---|---|---|---|---|---|
| 0 | contract | Migrations, DocumentTotals with N withholdings, geo reference data, entities, ports, DTOs, 501 routes, types, i18n/CSS/regression skeletons, menu placeholders | everything under "Contract (item 0)" | DocumentTotalsTest (N withholdings), MunicipalitiesTest, response shapes, TenantIsolationTest rows | — | — | — |
| 1 | account-picker | Search limit/words/total; postable = no active auxiliares; subdivide with move_references + AccountSubdivided handlers; picker hint; chart warning dialog | Ledger (`PostableAccounts`, `DoctrineLedgerCatalog`, `DoctrineAccountRepository`, `AddAccountHandler`, `LedgerJournalPoster`, `PostingRule`, outputs `account_postable`), `Catalog/Application/EventHandler/MoveProductAccounts`, `Party/Application/EventHandler/MoveTerceroAccounts`, `features/pick-account`, `widgets/chart-of-accounts`, i18n `ledger.chart.subdivide.*`, `ledger.picker.*` | PostableAccountsTest, AccountSearchApiTest (41359501 found by "4135" and "gravados"), SubdivideAccountApiTest (moves, audit), JournalPosterTest (account_has_auxiliaries), OBS-1 | CTA-01 – 09 | 0 | opus |
| 2 | withholdings-api | Lines with N retenciones on Sales and Purchasing: resolvers, drafts, aggregates, posting one line per account, PDF per-tax summary, usage query | Sales (`SalesInvoiceContent`, `InvoiceLineDraft`, `SalesInvoice`, `Quotation`, lines, `SalesInvoicePosting`, `QuotationPdf`, `SalesInvoicePdf`, outputs), Purchasing (`PurchaseDraftResolver`, `PurchaseLineDraft`, `PurchaseInvoice`, `PurchaseInvoiceLine`, `PurchaseInvoiceEntry`, `PurchaseInvoicePdf`, outputs), `DbalCatalogUsage`, README API rows of these endpoints | SalesInvoicePostingTest + PurchaseInvoicePostingTest (3 retenciones, odd cents), SalesInvoiceApiTest/PurchaseInvoiceApiTest (`withholdings`, alias, one-per-kind 422, kept snapshot), OBS-2, OBS-3 (posting half) | — | 0 | opus |
| 3 | withholdings-editor | The form: per-kind retención selects, grid cell, per-withholding totals preview, totals-panel rows, draft mapping in the three pages | `widgets/document-editor` (`types.ts`, `draft.ts`, `totals.ts`, `LinesGrid`, `TotalsPanel`, `lib/mapping`), `features/line-taxes`, `pages/*/model/draftMapping.ts` (quotations, sales-invoices, purchase-invoices), i18n `documentEditor.*` | totals.test.ts (same fixtures as DocumentTotalsTest), LineTaxesDialog.test.tsx, DocumentEditor.test.tsx, draftMapping tests | RET-01 – 12 | 0 | opus |
| 4 | terceros-city | País/Municipio on terceros and the company; required rules; missing filter and banner; municipality picker | Party (`Tercero`, `TerceroProfile`, inputs, outputs, `DoctrineTerceroQueries`, `TerceroController`), Company (`Company`, `CompanyInput/Output`, `UpdateCompanyProfileHandler`), `entities/municipality`, `pages/terceros`, `features/quick-create-tercero`, `widgets/company-settings`, i18n `terceros.geo.*`, `company.geo.*` | TerceroApiTest (CO needs municipio, foreign needs city, missing filter), CompanyApiTest, MunicipalityPicker.test.tsx, OBS-4 | CIU-01 – 09 | 0 | sonnet |
| 5 | product-code | `product_code` snapshot on write, in outputs, PDFs' Código column, read-only grid column | Sales/Purchasing write paths (after item 2), the three PDF twig templates and `*Pdf` classes, `LinesGrid` read-only column (after item 3), README rows | QuotationPdfTest (column), SalesInvoiceApiTest (`product_code` snapshot survives a rename), OBS-5 | COD-01 – 05 | 2, 3 | sonnet |
| 6 | journal-entry-tab | `GET /ledger/entries` by source; the widget; tabs on four document pages | Ledger (`DbalJournalQueries::bySource`, `LedgerReportController`), `widgets/journal-entry-view`, `pages/sales-invoices/ui/SalesInvoiceEditor.tsx`, `pages/purchase-invoices/ui/PurchaseInvoiceEditorPage.tsx`, `pages/cash-receipts`, `pages/supplier-payments` (detail views), i18n `journalEntry.*` | JournalEntriesBySourceApiTest (billing sees own document's entry, other company 404, reversal listed), JournalEntryView.test.tsx, OBS-6 | ASI-01 – 06 | 3 | sonnet |
| 7 | purchase-correct | *Corregir*: void + draft copy in one transaction, attachments copied, `active_number_key`, UI button and list hint | Purchasing (`CorrectPurchaseInvoiceHandler`, `PurchaseInvoice::markCorrected/voidNumberKey`, `DoctrinePurchaseInvoiceRepository`, `DuplicateSupplierInvoiceNumberSubscriber`, controller, outputs), `pages/purchase-invoices` (banner, modal, list), i18n `purchaseInvoice.correct.*` | CorrectPurchaseInvoiceApiTest (voided + draft, same number re-emits, allocations refuse, period locked, other company 404), OBS-7 | COR-01 – 06 | 2 | opus |
| 8 | tax-reports | Retenciones practicadas/sufridas, IVA report, *Según libros*, exports, the Impuestos page with its tabs | Reporting (`Application/Tax/*`, `DbalTaxQueries`, `TaxTables`, `TaxReportController`, outputs), `pages/tax-reports` (page, tabs for withholdings and IVA, `ExportLinks`), i18n `taxReports.*`, README rows | TaxQueriesTest (month grouping, voided excluded, ledger match), TaxReportApiTest (VIEW_BOOKS only, exports, `check=1`), OBS-3 (report half), OBS-8 | IMP-01 – 12 | 2 | opus |
| 9 | exogena | Formats 1001/1003/1005/1006/1007/1008/1009 working papers, thresholds and cuantías menores, concept table settings, blocking on missing municipio | Reporting (`Application/Exogena/*`, `DbalExogenaQueries`, `ExogenaTables`, `ExogenaController`), Ledger (`ExogenaConcept` handlers, `ExogenaConceptController`, provisioner), `pages/tax-reports` exógena tab, `widgets/exogena-concepts` (settings tab), i18n `taxReports.exogena.*`, `ledger.exogena.*` | ExogenaMinorAmountsTest, ExogenaApiTest (per format, threshold, missing municipio 422, other company), ExogenaConceptsApiTest, OBS-9 | EXO-01 – 08 | 4, 8 | opus |
| 10 | purchase-orders | Órdenes de compra: drafts, emission (OC), PDF, e-mail, void, duplicate, convert once to a draft purchase invoice, list and page | Purchasing (`PurchaseOrder` behaviour, handlers, `PurchaseOrderContents`, `PurchaseOrderPdf`, mailer, queries, controller), `pages/purchase-orders`, `widgets/purchase-order-list`, `features/convert-purchase-order`, numbering tab row, i18n `purchaseOrder.*`, README rows | PurchaseOrderLifecycleTest, ConvertPurchaseOrderApiTest (once, inactive supplier refused with the invoice's violations), no-entry assertion, OBS-10 | OC-01 – 14 | 2, 3, 5 | sonnet |

Waves (as `split.py plan` will print): 1 → items 1, 2, 3, 4; 2 → items 5, 6, 7, 8; 3 → items 9, 10.

Item 5 depends on 2 and 3 because the snapshot is written in the resolvers item 2 rewrites and shown in the grid item 3 rewrites. Item 6 waits for 3 so the two never touch the editor pages at once. Item 9 waits for 8 (same page) and 4 (municipio data). Item 10 waits for 5 so orders carry codes from day one.

## Decisions

- **Statuses on the wire**: purchase orders `draft`, `emitted`, `converted`, `voided` (`converted` instead of a quotation's `accepted`: the client's answer is the invoice). Tones: `converted` = success.
- **Error codes** (snake_case, stable): `account_has_auxiliaries`, `invalid_source`, `purchase_order_not_open`, `purchase_order_already_converted`, `exogena_terceros_incomplete`. Violation fields: `lines.N.withholdings`, `lines.N.withholdings.K.tax_id`, `municipality_code`, `city`, `country_code`.
- **`withholding_tax_id` stays in inputs and outputs as a deprecated alias** (first retención); it goes away in the next contract after the UI stops sending it (item 3 stops sending it).
- **`geo_municipality` has no `company_id`**: DANE reference data shared by all tenants, read-only after its migration; the exception to "every owned entity is CompanyOwned" is written here and in the README's data-model decisions.
- **`active_number_key`** keeps supplier-number uniqueness in the database across voids (NULL on voided rows); the handler's own check (`duplicate_supplier_invoice_number`) ignores voided invoices too.
- **Tax reports are document-based** with the books' figure next to them (Q-I); the exógena blocks on incomplete terceros rather than exporting blanks.
- **Per-kind selects, not a multi-select** for retenciones: enforces one per kind in the UI and reads as the accountant thinks (Renta / ReteIVA / ReteICA).
- **Model risks**: item 7 is opus for the uniqueness-key trick in one transaction (could be sonnet); item 10 is sonnet because it copies Quotation closely (could fail on the convert-with-violations path: relaunch one up if it does); item 4 is sonnet despite touching two contexts because every rule is a plain validation.

### Contract as built by item 0 (read before starting an item)

- **Schema:** migrations `Version20261108010107` (every table and column of §7 except the exógena seed; existing
  single retenciones moved to `<line table>_withholding`, product codes backfilled, `active_number_key` backfilled, an
  `OC` series per company; `down()` is irreversible) and `Version20261108010200` (DIVIPOLA, 1 122 rows). An item adds
  a migration only for a table it alone owns; `ledger_exogena_concept` is item 9's (it seeds the defaults per company
  with its provisioner and a data migration).
- **Lines** (`Shared\Domain\Model\CommercialLine`): constructors of `QuotationLine`, `SalesInvoiceLine`,
  `PurchaseInvoiceLine`, `PurchaseOrderLine` take `list<TaxSnapshot> $withholdings` (a "no tax" snapshot is skipped);
  `withholdings()` (entities, ordered), `withholdingTaxes()` (snapshots, what a copy passes on), deprecated
  `withholdingTax()` (the first, or none); `applyAmounts()` sets each retención's base (`TaxBase`) and share;
  `useProductCode()` / `productCode()` (item 5 writes it). `LineInput::$withholdings` and `LineAmounts::$withholdings`
  are lists. **Still single (item 2's):** `InvoiceLineDraft` / `PurchaseLineDraft` carry one `withholdingTax`, the
  aggregates wrap it (`[$line->withholdingTax]`), duplicate / convert copy only the first, posting
  (`SalesInvoicePosting`, `PurchaseInvoiceEntry`) and the PDFs read `withholdingTax()`; the inputs' `withholdings`
  is declared but no controller reads it yet.
- **Outputs:** every line output has `product_code` and `withholdings` (`Shared\UI\Http\Output\LineWithholdingOutput`,
  from `Shared\Application\Document\LineWithholdingView::ofLine()`); invoices, quotations and purchase orders have
  `withholdings_by_tax` (`WithholdingSummaryView::of()`). `DbalCatalogUsage` already reads the withholding tables.
- **Terceros and the company:** `country_code` / `municipality_code` columns and getters, no setters and no rules
  (item 4); outputs fill the two codes, `municipality_name` / `department_*` are null until item 4 fills them from
  `Shared\Application\Geo\Municipalities` (working, with `GET /geo/*`); inputs declare the fields, unused.
- **Purchase invoices:** `activeNumberKey` is kept by the constructor, `revise()` and `void()`; `supplierNumberTaken()`
  already ignores voided invoices. `correctedFromId` / `correctedById` / `purchaseOrderId` have getters only (items 7
  and 10 add the methods that set them).
- **Purchase orders:** `PurchaseOrder`, `PurchaseOrderLine`, `PurchaseOrderLineWithholding`, `PurchaseOrderStatus`
  (constructors and getters), `PurchaseOrderRepository` + Doctrine adapter, `PurchaseOrderNotFound`,
  `SeriesKind::PurchaseOrder`, `Numbering::purchaseOrder()`.
- **Ledger:** `AccountRepository::hasActiveChildren()` works; `JournalQueries::bySource()` throws until item 6;
  `AccountSubdivided` has a no-op `IgnoreAccountSubdivided` that item 1 deletes when it adds Catalog's and Party's
  handlers; `ExogenaConcept` + repository; `GET /accounts/search` answers `{items, total}` with `total` = the page's
  count until item 1 counts every match; `AccountOutput.moved` is null and the settings outputs' `account_postable`
  is true until item 1.
- **501 routes:** `PurchaseOrderController` (10), `PurchaseInvoiceController::correct` (7),
  `LedgerReportController::entries` (6), `TaxReportController` withholdings and vat (8) and exógena (9),
  `ExogenaConceptController` (9). `tests/Functional/Observations/ContractTest::unbuiltRoutes` lists them: **an item
  that builds a route removes its row there**.
- **Frontend:** `DraftLine.withholdings?: string[]` (optional; item 3 makes it the field and drops
  `withholding_tax_id`); placeholder pages `pages/purchase-orders` (`/ordenes-compra/*`, item 10) and
  `pages/tax-reports` (`/reportes/impuestos/*`, VIEW_BOOKS in the menu; items 8 then 9); empty slices
  `widgets/journal-entry-view` (6) and `widgets/purchase-order-list` (10); the menu marks the longest matching
  entry. i18n `journalEntry.json` (6), `taxReports.json` (8 owns it, 9 adds the `exogena` key), `purchaseOrder.json`
  (10); new keys under existing namespaces go under each item's prefix of the split table.
- **Regression and smoke:** sections 14–22 of `docs/tests/ui-regression.md` and one spec file per item in
  `backend/e2e/`, each with a comment naming its owner.

## Regression sections to add to `docs/tests/ui-regression.md`

14. Cuentas: picker y auxiliares — `CTA-01 – 09`; 15. Retenciones múltiples — `RET-01 – 12`; 16. Municipios — `CIU-01 – 09`; 17. Código en documentos — `COD-01 – 05`; 18. Contabilización — `ASI-01 – 06`; 19. Corregir factura de compra — `COR-01 – 06`; 20. Reportes de impuestos — `IMP-01 – 12`; 21. Exógena — `EXO-01 – 08`; 22. Órdenes de compra — `OC-01 – 14`. No prefix collides with the existing ones (ACC, CO, LED, TAX, TER, PRD, DOC, SAL, PUR, COT, RC, PAY, REP; strays AC, CUA, FAC, SRV).
