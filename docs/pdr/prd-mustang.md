# Mustang — Product roadmap

| | |
|---|---|
| Status | Draft v0.4 — staged roadmap |
| Date | 2026-10-02 |
| Author | Sergio Barbosa |
| Source | `docs/Mustang-1.pdf` (walkthrough notes and screenshots of a reference product) |
| Stage documents | Stage 1: [`prd-accounting.md`](prd-accounting.md). Later stages get their own `prd-<stage>.md` when they start |
| Scope of this document | Vision, principles that keep the product extensible, and the scope of every stage. Stack-agnostic |

> "Mustang" is the working codename taken from the source document. Product naming is an open question.

---

## 1. Vision

Mustang is a cloud business-management application for small and medium companies in **Colombia**. It starts from
the basic Colombian accounting concepts — the PUC chart of accounts, double-entry journal, terceros, IVA and
withholdings — and grows module by module: accounting first, then payroll, then inventory, then electronic
documents for DIAN and the rest of the commercial suite.

A company must be able to **use the product from stage 1**: set itself up, issue sales invoices, register supplier
invoices, collect and pay, and hand its accountant a balanced ledger. Each later stage adds a module without
rewriting what exists.

## 2. Product principles

These rules hold in every stage and are what make "always able to add more" true.

1. **One ledger, many sources.** Every module produces documents; every document that affects the books posts a
   balanced journal entry through the same posting mechanism. Payroll, inventory and future modules never write
   to accounts directly; they emit entries like an invoice does.
2. **Posting rules are data, not code.** Each accounting concept (revenue, IVA generado, IVA descontable,
   retenciones, clientes, proveedores, salario, cesantías, inventario, costo de ventas, …) maps to a PUC account in a
   company-level table that the accountant can edit. A new module adds concepts; it does not hard-code accounts.
3. **Terceros are shared.** Clients, suppliers, employees, banks and funds are one master with roles. Payroll reuses
   the employee tercero; inventory reuses the supplier tercero.
4. **Documents are immutable once emitted.** Changes happen through new documents (voids, notes, adjustments), so
   the audit trail is the document history.
5. **Yearly parameters live in tables.** SMMLV, auxilio de transporte, UVT, tax rates and DIAN code lists change by
   decree, so they are configurable data with validity dates, not constants.
6. **Colombia only, Spanish only, COP only** for the foreseeable future. Localisation hooks are not a goal.
7. **Multi-tenant from day one.** Every record belongs to one company; no query crosses companies.

## 3. Stages

| Stage | Name | Outcome for the company | Status |
|---|---|---|---|
| **1** | **Accounting** | Set up the company, quote and invoice clients, register supplier invoices, collect and pay, see a balanced ledger | Specified — [`prd-accounting.md`](prd-accounting.md) |
| **2** | **Payroll** | Register employees, run a monthly payroll with legal deductions, contributions and provisions, pay it, post it | Outline below |
| **3** | **Inventory** | Track stock per product and warehouse, value it, post cost of sales on each sale | Outline below |
| 4 | Commercial documents and DIAN electronic documents | Remissions, credit and debit notes; electronic invoicing and electronic payroll with DIAN | Outline below |
| 5 | Treasury and reporting | Manual vouchers, bank accounts and reconciliation, money transfers, financial statements, tax reports | Outline below |
| 6+ | Later | Fixed assets, budgets, multi-company users, public API, mobile | Not planned |

Stages 2 and 3 are the next two to build, in that order, unless the user base says otherwise. Stage 4 can be pulled
forward if electronic invoicing becomes a blocker for adoption.

### Stage 1 — Accounting (now)

Full specification in [`prd-accounting.md`](prd-accounting.md). In one paragraph: company setup with PUC chart and
posting rules; terceros; catalog of products and services (no stock); taxes and payment methods; **cotización**,
**factura de venta** and **recibo de caja**; **factura de compra / gasto** and **recibo de pago / egreso**; voiding by reversing entry;
journal, trial balance and receivable/payable ageing; users and roles; PDFs by e-mail.

Deliberately excluded from stage 1: inventory in any form (stock, warehouses, valuation, cost of sales; goods
purchases post to 6205 under the periodic system), remissions, credit and debit notes, electronic transmission to DIAN,
manual journal vouchers, inventory quantities, payroll.

### Stage 2 — Payroll (outline)

**Outcome.** The company registers employees with a contract and runs a monthly (or bi-weekly) payroll whose result
is posted to the ledger and paid through a payment document.

**Minimum scope.**

- Employee master on the shared tercero (contract type, start date, salary, risk class for ARL, EPS, AFP, caja de
  compensación, bank account).
- Yearly parameter table: SMMLV, auxilio de transporte, UVT, contribution rates, risk-class rates.
- Payroll period with one line per employee: devengados (salario, auxilio de transporte), deducciones (salud 4 %,
  pensión 4 %, fondo de solidaridad above 4 SMMLV), employer contributions (salud 8.5 %, pensión 12 %, ARL by class,
  caja 4 %, SENA 2 %, ICBF 3 %, with Art. 114-1 ET exemptions), provisions (cesantías 8.33 %, intereses 1 %, prima
  8.33 %, vacaciones 4.17 %).
- Posting: salary cost to 51xx / 52xx, net pay to 2505, contributions to 2370 and 2380, provisions to 2610.
- Payment of the period's net pay and a per-employee payslip (comprobante de nómina) PDF.

**Excluded from the first payroll release.** Overtime and recargos, incapacidades and licencias, variable pay and
commissions, settlement of a contract (liquidación), PILA file generation, nómina electrónica (stage 4).

### Stage 3 — Inventory (outline)

**Outcome.** Products of type *Producto* carry stock per warehouse, purchases increase it, sales decrease it, and
each sale posts a cost of sales entry.

**Minimum scope.**

- Warehouses (at least one default); stock balance per product and warehouse.
- The posting concept *compra de mercancías* switches from 6205 Compras (periodic) to 1435 Mercancías (perpetual);
  purchase invoice lines of inventory products post to 1435.
- Sales invoice lines of inventory products post cost of sales (6135) against 1435 at **weighted average cost**.
- Stock movements: entry by purchase, exit by sale, manual adjustment with reason, transfer between warehouses.
- Reports: stock on hand and valuation, kardex per product.
- Negative stock is blocked by default (company-level flag to allow it).
- Remisión (if stage 4 has shipped) starts moving stock: decide then whether dispatch uses a transit account or
  posts cost of sales directly.

**Excluded from the first inventory release.** FIFO or other costing methods, lots and serials, bills of materials,
reservations from quotations or remissions, barcodes and counting devices.

### Stage 4 — Commercial documents and DIAN electronic documents (outline)

- Remisión (no accounting effect, convertible to invoice; moves stock once stage 3 exists), nota crédito and nota débito (reference an invoice, reverse or extend its entry; from here on
  the only way to void an emitted invoice).
- Multiple invoicing resolutions and prefixes, exhaustion warnings.
- Electronic invoicing: UBL 2.1 XML, CUFE, digital signature, transmission and acknowledgement through a
  DIAN-authorised provider or direct connection; electronic credit and debit notes; documento soporte for purchases
  from non-invoicing suppliers; nómina electrónica for stage 2 payroll.

### Stage 5 — Treasury and reporting (outline)

- Comprobante contable (manual journal voucher with accountant approval), traslado de dinero between cash and bank
  accounts, bank accounts per payment method, bank reconciliation by statement import, ajuste de cartera y
  proveedores.
- Period closing, estado de resultados, balance general, flujo de efectivo, libros oficiales export, and the
  working papers for IVA, retención en la fuente and ICA returns.

## 4. Glossary

| Term | Meaning |
|---|---|
| **DIAN** | Dirección de Impuestos y Aduanas Nacionales, the Colombian tax authority |
| **PUC** | Plan Único de Cuentas, Decreto 2650 de 1993, the standard Colombian chart of accounts. Reference copy: [`docs/references/PUC-Decreto-2650-1993.pdf`](../references/PUC-Decreto-2650-1993.pdf) (source https://www.politecnicomayor.edu.co/virtual/documentos/PUC.pdf); browsable at https://puc.com.co/cuentas/ |
| **Tercero** | Any third party the business deals with: client, supplier, employee, bank, health or pension fund |
| **NIT / Cédula** | Tax ID for companies / national ID for people, among the DIAN document types that identify a tercero |
| **DV** | Dígito de verificación, the check digit of a NIT, computed by the DIAN algorithm |
| **RUT** | Registro Único Tributario, the tax registration that states a tercero's fiscal responsibilities |
| **Resolución de facturación** | DIAN authorisation to issue invoices: a prefix, a number range (*desde–hasta*) and a validity period |
| **Consecutivo** | Sequential document number. Invoices have an internal consecutive and the DIAN-authorised one |
| **IVA** | Value-added tax (0 %, 5 %, 19 %). *Generado* on sales, *descontable* on purchases |
| **Impoconsumo** | National consumption tax (e.g. 8 %, or a fixed value per unit) |
| **Retención (ReteFuente, ReteIVA, ReteICA)** | Withholding taxes that the payer deducts and reports on behalf of the payee |
| **Recibo de caja** | Cash receipt: records a client payment and applies it to invoices |
| **Recibo de pago / egreso** | Payment voucher: records a payment to a supplier and applies it to purchase invoices |
| **Remisión** | Delivery note that accompanies goods; no accounting effect |
| **Cotización** | Quotation; same shape as an invoice, no accounting effect |
| **Nota crédito / débito** | Credit / debit note that decreases / increases an issued invoice |
| **SMMLV** | Salario mínimo mensual legal vigente, the legal monthly minimum wage |
| **UVT** | Unidad de valor tributario, the yearly tax unit of value |
| **Prestaciones sociales** | Legal employee benefits accrued monthly: cesantías, intereses a las cesantías, prima, vacaciones |
| **Kardex** | Per-product record of stock movements and valuation |

## 5. Cross-stage non-functional requirements

- **Multi-tenant** isolation on every query; one user belongs to one company in stages 1–3.
- **Auditability**: emitted documents and posted entries are immutable; all creates, emissions, voids and setting
  changes are logged with user and timestamp.
- **Precision**: money uses decimal arithmetic with at least two decimals; totals are rounded once, at document
  level, as the DIAN technical annex requires.
- **Retention**: documents, PDFs and entries kept at least 5 years, with daily backups and point-in-time recovery.
- **Performance**: lists under 1 s for companies with up to 100 000 documents; document emission under 2 s.
- **Security**: encrypted in transit and at rest, role-based access, rate-limited authentication.
- **Data protection**: terceros' personal data handled under Ley 1581 de 2012; exportable and erasable on request.
- **Responsive web UI** for laptop and tablet; Spanish (Colombia) copy, `DD/MM/YYYY`, COP with thousands separator.

## 6. Open questions (product-wide)

| # | Question | Stage affected |
|---|---|---|
| OQ-1 | Product name: is "Mustang" the codename or the brand? | All |
| OQ-2 | Build DIAN electronic documents in-house or integrate an authorised provider? | 4 (data stored from 1) |
| OQ-3 | Which 41xx revenue account each company uses (4135 goods, 4155 services, other by activity). Discounts settled: 4175 *Devoluciones, rebajas y descuentos en ventas (DB)* | 1 |
| OQ-4 | Semantics of NC-1 vs NC-2 and whether a credit note may target a paid invoice | 4 |
| OQ-5 | Can a user belong to several companies (an accountant with many clients)? | 1 model, later UX |
| OQ-6 | Which withholdings the company applies and suffers (ReteFuente concepts, ReteIVA, ReteICA by municipality), and their rates | 1 |
| OQ-7 | Payroll frequency to support first: monthly only, or monthly and quincenal | 2 |
| OQ-8 | Costing method confirmed as weighted average, or does any target customer need FIFO | 3 |
| OQ-9 | Price lists per product or per client — single price in stage 1, revisit | 4 |
