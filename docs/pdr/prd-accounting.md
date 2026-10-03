# Stage 1 — Accounting

| | |
|---|---|
| Status | Draft v0.4 — stage specification (inventory explicitly excluded; periodic purchases) |
| Date | 2026-10-02 |
| Author | Sergio Barbosa |
| Parent | [`prd-mustang.md`](prd-mustang.md) (roadmap, principles, glossary, cross-stage NFRs) |
| Sources | `docs/Mustang-1.pdf`; **PUC — Decreto 2650 de 1993** ([`docs/references/PUC-Decreto-2650-1993.pdf`](../references/PUC-Decreto-2650-1993.pdf), from https://www.politecnicomayor.edu.co/virtual/documentos/PUC.pdf); product principles in the roadmap |
| Scope of this document | Product requirements for the first usable release. Stack-agnostic; the technical plan is appended at the end when implementation starts |

---

## 1. What it is for

A small Colombian company sets itself up, **invoices its clients**, **registers its suppliers' invoices**, records what
it collects and pays, and hands its accountant a **balanced ledger on the PUC** — without typing a single journal
entry by hand. This is the bare minimum for a company to run on Mustang, and the foundation every later stage posts
into.

Users: the **owner/administrator**, one or more **billing users**, and the invited **accountant** (roles in §8).

## 2. In and out of scope

**In scope**

1. Company setup: identity, invoicing resolution, posting rules, chart of accounts seeded from the PUC.
2. Masters: terceros, products and services (catalog only), taxes, payment methods.
3. Sales: cotización, factura de venta, recibo de caja.
4. Purchases: factura de compra / gasto, recibo de pago / egreso.
5. Voiding an emitted document by reversing entry.
6. Ledger: automatic posting, libro diario, balance de prueba, cartera (receivables and payables by tercero).
7. Users, roles, PDFs and e-mail sending.

**Out of scope (see roadmap stages)**

- Remisión, nota crédito, nota débito, multiple resolutions (stage 4).
- Electronic transmission to DIAN (stage 4). The fields DIAN needs are stored from this stage.
- Payroll (stage 2).
- **Inventory (stage 3) is not touched in this stage**: no stock quantities, no warehouses, no valuation, no cost of
  sales posting. Products are catalog items; purchases of goods post to 6205 Compras de mercancías (sistema
  periódico) and the accountant adjusts inventory at period close outside Mustang.
- Manual journal vouchers, bank reconciliation, money transfers, financial statements (stage 5).
- Multi-currency, multi-company users, mobile apps.

## 3. Domain model

```
Company ─┬─ ChartOfAccounts (PUC) ── Account
         ├─ PostingRule (concept → account)
         ├─ InvoicingResolution
         ├─ Tax, PaymentMethod
         ├─ Tercero ──< Contact            roles: cliente | proveedor | empleado | otro
         ├─ Product                        tipo: producto | servicio (no stock in this stage)
         ├─ Quotation ──< Line                  no accounting effect; convertible to SalesInvoice
         ├─ SalesInvoice ──< Line, PaymentLine, Receivable(due date, balance)   origin: Quotation?
         ├─ CashReceipt   ──< Allocation → Receivable
         ├─ PurchaseInvoice ──< Line, PaymentLine, Payable(due date, balance)
         ├─ SupplierPayment ──< Allocation → Payable
         ├─ JournalEntry ──< JournalLine (account, débito, crédito, tercero)   source: any document above
         └─ User (role)
```

Every document in `emitido` state owns exactly one journal entry; voiding adds a second, reversing entry and never
edits the first.

## 4. Functional requirements

Field names are in Spanish as they appear in the UI. Behaviour rules follow each field list.

### 4.1 Company setup

**Fields**

- Razón social, nombre comercial, NIT and DV, tipo de documento, dirección, ciudad, teléfono, correo, logo.
- Régimen de IVA and responsabilidades fiscales (same code list as terceros, §4.2).
- **Impuestos por defecto**: impuesto cargo and impuesto retención pre-selected on new products and lines.
- **Resolución de facturación** (one in this stage): número de resolución, prefijo, desde, hasta, fecha inicio,
  fecha fin, modalidad (electrónica / manual), consecutivo actual.
- **Numeración interna** for cotizaciones, recibos de caja, facturas de compra and recibos de pago: prefijo and
  próximo número.
- **Fecha de bloqueo contable**: no document may be emitted or voided with a date on or before it (the accountant
  moves it forward at period close).

**Rules**

- The resolution is active when today is within its dates and consecutivo < hasta. Emission outside it is blocked.
  The owner is warned when fewer than a configurable number of invoices or days remain.
- Sales invoices must be electronic by law. *Modalidad manual* is only selectable after the administrator confirms
  the company holds the DIAN permission; the confirmation is logged.
- On creation the company receives the **PUC chart** (classes, groups, accounts and sub-accounts) and the default
  **posting rules** (§5). Standard accounts cannot be deleted; the accountant may add sub-accounts and deactivate
  unused ones.

### 4.2 Terceros

One master for everyone the company deals with. A tercero may hold several roles.

| Group | Fields |
|---|---|
| Tipo de tercero | **Cliente**, **Proveedor**, **Empleado**, **Otro** (bancos, EPS, fondos, …), any combination |
| Datos básicos | Tipo (Persona / Empresa); Tipo de identificación (Cédula de ciudadanía, NIT, Cédula de extranjería, Pasaporte, Tarjeta de identidad, …); Nro. identificación; DV (computed for NIT, editable); Código de sucursal (default 0); Nombres y Apellidos (persona) or Razón social (empresa); Nombre comercial; Ciudad; Dirección; one or more phones (indicativo, número, extensión) |
| Datos para facturación y envío | Nombre y apellido del contacto; Correo electrónico; Celular (+57); Código postal; Tipo de régimen IVA; *Contacto marcado como pagador* |
| Responsabilidad fiscal | Multi-select: O-13 Gran contribuyente, O-15 Autorretenedor, O-23 Agente de retención IVA, O-47 Régimen simple, R-99-PN No aplica – Otros. R-99-PN default; the UI asks to verify against the RUT |
| Contactos | Zero or more named contacts selectable on documents |
| Cuentas contables | Optional per-tercero override of the receivable (1305xx) and payable (2205xx / 2335xx) accounts; defaults come from posting rules |

**Rules**

- Tipo + número de identificación unique per company.
- *Autocompletar datos* queries an external registry when configured; the form works without it.
- **Quick-create** (tipo, documento, número, DV, nombres, contacto) from inside any document; full form from the
  list.
- A tercero referenced by any document cannot be deleted, only deactivated.
- *Vendedor* on sales documents is a tercero with role Empleado.

### 4.3 Products and services

**Fields**: Tipo (Producto / Servicio); Categoría; Código; Nombre; Descripción larga; Unidad de medida DIAN
(default `94 – unidad`); Impuesto cargo and Impuesto retención by default; Precio de venta (COP) with *Incluir IVA en
el precio*; Cuenta de ingreso (defaults from posting rules); Cuenta de gasto / costo for purchases (defaults from
posting rules).

**Rules**

- Código unique per company. Quick-create from a document line; *Creación completa* opens the full form.
- With *Incluir IVA en el precio*, the unit value placed on a line is the price net of IVA and the line total equals
  the list price.
- No quantities are tracked and there is one sale price per product (OQ-9 closed). Cantidad exists only on
  document lines. The model keeps *Tipo* so stage 3 can attach stock to products without migration.

### 4.4 Taxes

Company-level catalog, seeded, editable by the accountant. Each tax: nombre, clase, tipo de cálculo (porcentaje or
valor fijo por unidad), tarifa, and the accounts it posts to (one for sales, one for purchases).

| Clase | Seeded values |
|---|---|
| Impuesto cargo | Ninguno, IVA 19 %, IVA 5 %, IVA 0 %, IVA por servicios 19 %, Impoconsumo 8 %, Impoconsumo por valor |
| Retención | Ninguno; ReteFuente by concept (e.g. servicios 4 %, compras 2.5 %, honorarios 10 %/11 %), ReteIVA 15 %, ReteICA by municipality (company-defined) |

A tax used by any document cannot be deleted, only deactivated. Rates carry validity dates.

### 4.5 Payment methods

Each method maps to a PUC account and has a kind: **contado** (cash, card, bank) or **crédito** (creates a
receivable or payable with a due date).

| Nombre | Cuenta | Kind |
|---|---|---|
| Efectivo | 11050501 Caja general | contado |
| Tarjeta débito | 11100501 Bancos moneda nacional | contado |
| Tarjeta crédito | 11100501 Bancos moneda nacional | contado |
| Transferencia | 11100501 Bancos moneda nacional | contado |
| Crédito | 1305 Clientes (sales) / 2205 Proveedores (purchases) | crédito |

Credit terms: *Hoy*, *a 15 días*, *a 30 días*, *a 60 días*, or a custom date, giving *fecha de vencimiento*. Users may
create more methods (*+ Crear nuevo*) by naming them and choosing an account.

### 4.6 Shared document behaviour

Applies to cotización, factura de venta and factura de compra. Cotización has no *Formas de pago* and no
accounting effect; everything else below applies to it.

- **Header**: Tipo (numbering series), Número, Tercero (search with minimum 3 characters and *+ Crear nuevo*),
  Contacto, Fecha de elaboración (default today).
- **Lines**: Producto/Servicio (search, quick-create) **or**, on purchases, a direct expense account; Descripción;
  Cantidad; Valor unitario; % Descuento; Impuesto cargo; Impuesto retención; Valor total. A per-line tax dialog shows
  the subtotal, lets the user change both taxes and offers *Aplicar estos impuestos al producto de ahora en adelante*.
- **Totals**:

  ```
  Total bruto  = Σ cantidad × valor unitario
  Descuentos   = Σ cantidad × valor unitario × % descuento
  Subtotal     = Total bruto − Descuentos
  Impuestos    = Σ impuesto cargo de cada línea, sobre la base con descuento
  Retenciones  = Σ impuesto retención de cada línea (+ ReteICA documental si aplica)
  Total neto   = Subtotal + Impuestos − Retenciones
  ```

  Computed with decimal arithmetic, at least two decimals, rounded once at document level.
- **Formas de pago**: one or more rows (método, valor; due date for crédito). *Total formas de pago* must equal
  *Total neto* to emit; a check mark shows when they match.
- **Footer**: Observaciones, attachments.
- **Actions**: Cancelar, Guardar (borrador), Emitir, Emitir y enviar (e-mail PDF to the billing contact).
- **Lifecycle**: `borrador → emitido → pagado parcialmente → pagado`, plus `anulado` from any emitted state.
  Only drafts are editable.

### 4.7 Cotización

- Same header, lines and totals as an invoice (§4.6). Extra fields: *Responsable de la cotización* (Empleado),
  rich-text *Encabezado* and *Condiciones comerciales*, *Fecha de vencimiento* of the offer.
- Numbering series `C` from the numeración interna; no resolution involved.
- Lifecycle: `borrador → emitida → aceptada | rechazada | vencida`, plus `anulada`. Emitting freezes it and allows
  sending the PDF; it posts **no journal entry** and never affects cartera.
- **Convertir a factura** creates a draft factura de venta with the same client, contact, lines and taxes; the
  invoice records the quotation as its origin and the quotation becomes `aceptada`. A quotation converts at most
  once in this stage.

### 4.8 Factura de venta

- Tipo selects the invoicing resolution; Número is the resolution's next consecutive, read-only. The **internal
  consecutive** (all invoices) and the **authorised consecutive** (per resolution) are both stored.
- Vendedor (Empleado) is optional.
- Emission consumes the number, freezes the document, creates one receivable per credit payment line and posts:

| Débito | Crédito |
|---|---|
| Payment-method account per contado line; 1305 Clientes (tercero) per crédito line | Ingresos (posting rule *ingreso*, or the product's account) — Subtotal |
| 1355 Anticipo de impuestos (retención practicada por el cliente) — Retenciones | 2408 IVA generado — Impuestos |

Discount posting follows posting rule *descuento en ventas* (OQ-3).

- PDF: logo, resolution text, numbering, fiscal data of both parties, lines, totals, taxes, observaciones, and the
  DIAN fields that electronic invoicing will need (stored now, transmitted in stage 4).

### 4.9 Recibo de caja

- Fields: Tipo (`RC`), Número (automatic), Cliente, Fecha, *Dónde ingresa el dinero* (contado payment method),
  Valor recibido, Observaciones, attachments.
- On choosing the client the module lists every open receivable: factura, vencimiento, valor, saldo. The user
  allocates the received amount; allocations must sum to Valor recibido. Over-payment is not allowed in this stage.
- Posting: Dr payment-method account; Cr 1305 Clientes (tercero). Receivables and invoice status update.
- *Guardar y enviar por mail* sends the receipt PDF.

### 4.10 Factura de compra / gasto

- Fields: Proveedor; **Número de factura del proveedor** (external, unique per proveedor); Fecha de la factura;
  Fecha de vencimiento; Número interno (automatic); lines as in §4.6 where each line is either a catalog item or an
  **expense account** chosen directly (e.g. 5135 Servicios, 5120 Arrendamientos); Formas de pago; Observaciones;
  attachment of the supplier's PDF/XML.
- Taxes on a purchase line: IVA descontable (impuesto cargo) and the **retenciones the company practices** on the
  supplier (ReteFuente, ReteIVA, ReteICA) according to the supplier's responsabilidades fiscales and the company's
  agent status.
- Emission creates one payable per credit line and posts:

| Débito | Crédito |
|---|---|
| Gasto / costo account per line (product's account or the chosen account) — Subtotal | Payment-method account per contado line; 2205 Proveedores (tercero) per crédito line |
| 2408 IVA descontable — Impuestos | 2365 ReteFuente / 2367 ReteIVA / 2368 ReteICA — Retenciones |

### 4.11 Recibo de pago / egreso

- Mirror of the cash receipt for suppliers: Proveedor, Fecha, *De dónde sale el dinero* (contado payment method),
  Valor pagado, allocations to open payables, Observaciones, attachments.
- Posting: Dr 2205 Proveedores (tercero); Cr payment-method account. Payables and invoice status update.

### 4.12 Anulación (void)

- An emitted invoice (sale or purchase) can be voided while no receipt or payment is allocated to it; otherwise the
  allocations must be voided first. A receipt or payment can be voided at any time.
- Voiding posts a **reversing entry** dated the void date (not before the fecha de bloqueo), sets the document to
  `anulado`, keeps its number and records the reason and user. The PDF shows *ANULADA*.
- Once electronic invoicing exists (stage 4), voiding an electronic invoice will require a nota crédito; the model
  keeps the void reason and the reversing entry so that path can be added without migration of existing data.

### 4.13 Ledger and reports

- **Libro diario**: journal entries by date with source document link, filter by date, account, tercero.
- **Balance de prueba**: per account and period, opening balance, débitos, créditos, closing balance, drill-down to
  entries. Σ débitos = Σ créditos always.
- **Cartera de clientes** and **cartera de proveedores**: open receivables/payables by tercero with ageing buckets
  (al día, 1–30, 31–60, 61–90, > 90 días).
- **Plan de cuentas**: browse and edit the chart (accountant).
- All reports export to CSV and PDF.

### 4.14 Users and roles

- Owner invites users by e-mail with a role; *Invitar a tu contador* invites an Accountant.
- E-mail and password authentication, password reset by e-mail, session expiry on inactivity.
- Every document records who created it, who emitted it, who voided it, and when.

### 4.15 Lists

Every module has a list with search, status filter, date range and pagination; row actions: open, duplicate (as a
new draft), download PDF, send by e-mail, void (where allowed).

## 5. Posting rules (default account map)

Company-level, editable by the accountant, seeded as follows. Concepts are the extension point for later stages.

| Concept | Default PUC account | Notes |
|---|---|---|
| ingreso | 41xx by economic activity: 4135 Comercio al por mayor y al por menor (goods), 4155 Actividades empresariales (services) | Chosen per company at setup; overridable per product. OQ-3 |
| descuento en ventas | 4175 Devoluciones, rebajas y descuentos en ventas (DB) | Confirmed in the PUC (name per D.R. 2894/94). Discounts on the invoice are posted gross: revenue at full value, discount as a débito here |
| iva generado | 240805 | 2408 has no standard sub-accounts (240801–240899 are free), so 240805 is a company auxiliar |
| iva descontable | 240810 | Company auxiliar under 2408 |
| retención sufrida (clientes nos retienen) | 135515 Retención en la fuente | Under 1355 Anticipo de impuestos |
| reteiva sufrida | 135517 Impuesto a las ventas retenido | |
| reteica sufrida | 135518 Impuesto de industria y comercio retenido | |
| clientes | 130505 Nacionales (auxiliar 13050501) | 130510 Del exterior; overridable per tercero |
| proveedores | 2205 Nacionales (auxiliar 22050501) | PUC 2205 covers goods **and** services; 2335 Costos y gastos por pagar covers services, honorarios and financial costs. Default 2205, overridable per tercero. OQ-10 |
| retefuente practicada | 2365 Retención en la fuente | Standard sub-accounts by concept: 236515 Honorarios, 236520 Comisiones, 236525 Servicios, 236530 Arrendamientos, 236540 Compras, 236570 Otras |
| reteiva practicada | 2367 Impuesto a las ventas retenido | Sub-accounts 236701–236799 free |
| reteica practicada | 2368 Impuesto de industria y comercio retenido | Sub-accounts 236801–236899 free |
| gasto por defecto | 5195 Diversos | Used when a purchase line has no product and no account chosen; overridable per product. Common choices: 5105 Gastos de personal, 5110 Honorarios, 5120 Arrendamientos, 5135 Servicios |
| caja / bancos | per payment method | 110505 Caja general, 111005 Bancos moneda nacional (auxiliares 11050501, 11100501) |
| compra de mercancías | 6205 Compras de mercancías | Sistema periódico: goods bought for resale are a cost of the period. Stage 3 switches this concept to 1435 Mercancías (sistema permanente) |

Per Art. 6 of the Decreto, the catálogo is mandatory down to the sub-account (6 digits); the company may use its own
auxiliares (8+ digits) provided a table of equivalences exists. Mustang therefore seeds the official catálogo to 6
digits and creates the auxiliares above as company accounts under their official parents.

**Invariants**

1. Every entry balances to the cent.
2. Entries are created and reversed only by documents; no manual editing in this stage.
3. For each client: balance in 1305 = Σ emitted sales invoices − Σ receipts − Σ voided amounts. For each supplier:
   balance in 2205 = Σ emitted purchase invoices − Σ payments − Σ voided amounts.
4. No entry is dated on or before the fecha de bloqueo contable.

## 6. Compliance notes for this stage

- Resolution handling, decimal precision, DIAN document types, unidades de medida and responsabilidades fiscales are
  implemented now because stage 4 cannot retrofit them into emitted documents.
- Purchase invoices store the supplier's number and attachment so the documento soporte / IVA descontable evidence
  exists for the accountant.
- Personal data of terceros under Ley 1581 de 2012: exportable and erasable on request.

## 7. Acceptance criteria

1. A new company is set up with PUC chart, posting rules, default taxes, payment methods and one resolution in
   under 10 minutes without documentation.
2. A quotation for a client is emitted and sent; the libro diario shows no entry for it. Converting it yields a
   draft invoice with identical lines and totals, and the quotation shows `aceptada`.
3. A billing user creates a client and a service inline, emits an invoice paid half in cash and half at 30 days;
   the journal shows one balanced entry debiting caja and 1305 and crediting ingresos and 2408; cartera shows one
   receivable due in 30 days.
4. A cash receipt for the receivable marks the invoice `pagado`, credits 1305 for exactly the amount, and the
   client's 1305 balance is zero.
5. A purchase invoice for a service with IVA 19 % and ReteFuente 4 % on credit posts Dr gasto, Dr IVA descontable,
   Cr 2365, Cr 2205, balanced; cartera de proveedores shows the payable at its net amount.
6. A supplier payment clears that payable; the balance de prueba still balances.
7. Voiding an unpaid invoice produces a reversing entry, status `anulado`, PDF marked ANULADA, and the number is not
   reused.
8. Emission beyond the resolution's *hasta* or dates is impossible; the owner was warned beforehand.
9. No entry can be dated on or before the fecha de bloqueo.
10. A user of company A cannot see or reach any record of company B.

## 8. Roles

| Role | Can |
|---|---|
| Owner / administrator | Everything, including company settings, users, posting rules |
| Billing user | Terceros, products, sales and purchase documents, receipts and payments, lists and PDFs |
| Accountant | Everything the billing user can read; edit chart of accounts, taxes, posting rules, fecha de bloqueo; view ledger and reports; cannot emit commercial documents |

## 9. Open questions for this stage

Each question carries the default the specification assumes. "Blocking" questions change the data model or the
posting rules and should be answered before implementation starts; the others can be answered during the build.

### Accounting and tax

| # | Question | Default assumed | Blocking |
|---|---|---|---|
| Q1 | Which 41xx revenue account does the company use (4135 goods, 4155 services, other by activity)? Chosen once at setup, or per product? | Chosen at setup from a short list; overridable per product | Yes |
| Q2 | Do we support companies that are **no responsables de IVA** (invoice without IVA) and companies in the **Régimen Simple de Tributación** (no retención practicada, different tax posting)? | Responsable de IVA, régimen ordinario only; the company flag exists but other regimes are not implemented | Yes |
| Q3 | Which retenciones does the target company practice and suffer (ReteFuente concepts and rates, ReteIVA, ReteICA municipalities), and is it an agente de retención? Thresholds in UVT? | Seed ReteFuente servicios 4 %, compras 2.5 %, honorarios 10 %/11 %, ReteIVA 15 %, no ReteICA; no UVT thresholds, user picks the tax per line | Yes |
| Q4 | When is the retención sufrida recorded: at invoice emission (as the reference product does) or at recibo de caja when the client actually withholds? | At invoice emission, as specified; the receipt allocates the net | Yes |
| Q5 | Service payables to 2205 Proveedores or 2335 Costos y gastos por pagar? | 2205 default, overridable per tercero | No |
| Q6 | Does a company need to load **saldos iniciales** (opening balances for 1305, 2205, caja, bancos, patrimonio) when it starts on Mustang mid-life? | Not in stage 1; the company starts from zero | Yes |
| Q7 | Is Impoconsumo needed by the first target companies (restaurants, bars, some goods)? | Seeded as a tax but no special posting beyond a liability account | No |
| Q8 | Precision policy: 2 decimals on amounts, how many on unit prices and rates? | 2 decimals stored on money, 4 on unit price and percentage, rounding once per document | No |

### Documents

| # | Question | Default assumed | Blocking |
|---|---|---|---|
| Q9 | Credit sales: exactly one due date per invoice, or several instalments (cuotas)? | One due date per credit payment line | Yes |
| Q10 | May an emitted sales invoice be voided directly with a reversing entry in stage 1, given that electronic invoices will later require a nota crédito? | Yes, direct void while no receipt is allocated | No |
| Q11 | Backdating: may a user emit a document with a date earlier than today (within the open period)? | Yes, any date after the fecha de bloqueo and not in the future | No |
| Q12 | One invoicing resolution per company in stage 1, or several (e.g. one electronic and one manual/talonario)? | One | No |
| Q13 | Purchases from suppliers who are not obliged to invoice: do we need the **documento soporte** in stage 1 for the expense to be deductible, or is registering the supplier's document enough? | Register only; documento soporte is stage 4 | No |
| Q14 | Purchase lines posted directly to an account: restrict the picker to class 5, 6 and 7? | Yes, plus any account the accountant marks as "usable on purchases" | No |
| Q15 | Import of the supplier's electronic invoice XML to pre-fill a purchase invoice? | Out of scope; attachment only | No |
| Q16 | Recibo de caja: anticipos (payment without an invoice) and over-payment? | Not allowed; allocations must equal the amount received | No |
| Q17 | Cotización: default validity in days, and may a quotation be converted more than once (partial acceptance)? | 30 days; convert once | No |

### Masters

| # | Question | Default assumed | Blocking |
|---|---|---|---|
| Q18 | Bank accounts: model each bank account as its own payment method and 1110 auxiliar, or one generic "Bancos"? | One payment method per bank account, each with its own auxiliar | No |
| Q19 | Autocompletar datos of a tercero from an external registry (RUES / DIAN): in scope? | Out of scope; manual entry with DV auto-computed | No |
| Q20 | Product categories: flat list or hierarchy? | Flat list | No |
| Q21 | Unidad de medida DIAN on products: full code list in stage 1, or default "94 – unidad" with a few common ones? | Short list (unidad, kilogramo, metro, hora, servicio), full list in stage 4 | No |

### Users, tenancy and output

| # | Question | Default assumed | Blocking |
|---|---|---|---|
| Q22 | One company per user, or may an accountant log into several companies? | One company per user; the model keeps user and company separate so this can change | Yes |
| Q23 | Are three roles (owner, billing user, accountant) enough? May the accountant void documents or edit terceros? | Three roles; accountant is read-only on documents and terceros | No |
| Q24 | Onboarding: self-signup that creates a company, or invitation only? | Self-signup creates the company and its owner | No |
| Q25 | Reports: are libro diario, balance de prueba and cartera enough, or do you want a basic estado de resultados and balance general in stage 1? | Add the two basic statements; they derive from the balance de prueba | No |
| Q26 | Export formats: CSV and PDF only, or also Excel (.xlsx)? | CSV and PDF | No |
| Q27 | Sending PDFs by e-mail in stage 1, or download only? | E-mail sending included | No |
| Q28 | UI copy in Spanish only; code identifiers in English? | Yes | No |

## Appendix A — PUC mapping per document

Reference: Decreto 2650 de 1993, catálogo and *descripciones y dinámicas*
([`docs/references/PUC-Decreto-2650-1993.pdf`](../references/PUC-Decreto-2650-1993.pdf)). Each attribute of a
document that carries money maps to a posting-rule concept (§5), and the concept to an account. Débito/Crédito
columns follow the account *dinámica* in the Decreto. Codes in **bold** are official sub-accounts; the 8-digit
auxiliares are Mustang's defaults under them.

### A.1 Factura de venta (stage 1)

| Attribute | Amount | Débito | Crédito | Dinámica basis |
|---|---|---|---|---|
| Forma de pago contado (efectivo, tarjeta, transferencia) | per payment line | **110505** Caja general / **111005** Bancos | | 1105/1110: débito por ingresos de dinero |
| Forma de pago crédito | per payment line | **130505** Clientes nacionales (tercero), **130510** del exterior | | 1305 (a): "productos, mercancías o servicios vendidos a crédito" |
| Valor unitario × cantidad (gross revenue) | Total bruto | | **41xx** Ingresos operacionales by activity (4135 / 4155 …) | 41xx: crédito "por el valor de los ingresos por venta" |
| % Descuento | Descuentos | **4175** Devoluciones, rebajas y descuentos en ventas (DB) | | 4175: débito por rebajas y descuentos |
| Impuesto cargo IVA | Impuestos | | **2408** Impuesto sobre las ventas por pagar, auxiliar 240805 IVA generado | 2408: crédito por el IVA facturado |
| Impuesto cargo Impoconsumo | Impuestos | | **2495** Otros / company auxiliar for impoconsumo | Liability to the DIAN |
| Impuesto retención practicada por el cliente (ReteFuente) | Retenciones | **135515** Retención en la fuente | | 1355 (b): "retenciones practicadas al ente económico" |
| ReteIVA sufrida | Retenciones | **135517** Impuesto a las ventas retenido | | 1355 (c) |
| ReteICA sufrida | Retenciones | **135518** Impuesto de industria y comercio retenido | | 1355 (d) |

Check: Σ débitos = Total neto + Retenciones + Descuentos = Σ créditos = Total bruto + Impuestos.

### A.2 Recibo de caja (stage 1)

| Attribute | Débito | Crédito | Dinámica basis |
|---|---|---|---|
| Dónde ingresa el dinero | **110505** / **111005** | | |
| Allocation to invoice | | **130505** / **130510** (tercero) | 1305 (a) crédito: "pagos efectuados por los clientes" |

### A.3 Factura de compra / gasto (stage 1)

| Attribute | Amount | Débito | Crédito | Dinámica basis |
|---|---|---|---|---|
| Line with expense account or service | Subtotal | **5xxx** Gastos (5105, 5110, 5120, 5135, 5195 …) or **6xxx**/**7xxx** when it is a cost | | Class 5/6/7: débito por la causación |
| Line with product of type Producto | Subtotal | **6205** Compras de mercancías (sistema periódico) | | 6205 (a): "adquisiciones realizadas durante el período" |
| % Descuento | Descuentos | | Net on the expense or 6205 line (the supplier's invoice already shows the discount) | |
| IVA descontable | Impuestos | **2408** auxiliar 240810 IVA descontable | | 2408 débito por IVA descontable |
| Forma de pago crédito | per payment line | | **2205** Proveedores nacionales (tercero) / **2210** del exterior, or **2335** Costos y gastos por pagar | 2205 (a): "por el valor de la factura"; 2335 (a): "servicios recibidos" |
| Forma de pago contado | per payment line | | **110505** / **111005** | |
| ReteFuente practicada | Retenciones | | **2365xx** by concept (236525 Servicios, 236540 Compras, 236515 Honorarios, 236530 Arrendamientos) | 2365: crédito "por el importe de la retención que debe efectuar" |
| ReteIVA practicada | Retenciones | | **2367** Impuesto a las ventas retenido | 2367 (a) |
| ReteICA practicada | Retenciones | | **2368** Impuesto de industria y comercio retenido | 2368 (a) |

### A.4 Recibo de pago / egreso (stage 1)

| Attribute | Débito | Crédito | Dinámica basis |
|---|---|---|---|
| Allocation to purchase invoice | **2205** / **2210** / **2335** (tercero) | | 2205 (a) débito: "abono o cancelación de la factura" |
| De dónde sale el dinero | | **110505** / **111005** | |

### A.5 Cotización (stage 1)

No journal entry. Same attributes as A.1 so that conversion to an invoice carries them unchanged.

### A.6 Remisión (stage 4)

No journal entry. The invoice that fulfils the remisión posts revenue as in A.1. Any stock effect belongs to the
inventory stage and is specified there, not here.

### A.7 Nota crédito (stage 4) — reverses an invoice in part or in full

| Attribute | Débito | Crédito | Dinámica basis |
|---|---|---|---|
| Lines credited (devolución or rebaja) | **4175** Devoluciones, rebajas y descuentos en ventas (DB) | | 4175 (a): "por el valor de las devoluciones" |
| IVA on credited lines | **2408** / 240805 | | Reversal of IVA generado |
| Retenciones on credited lines | | **1355xx** | 1355 (c) crédito: "devoluciones y/o anulaciones" |
| Client balance (unpaid invoice) | | **1305xx** (tercero) | 1305 (e) crédito: "notas crédito que origine el ente económico a favor de sus clientes" |
| Refund (paid invoice) | | **110505** / **111005**, or **2380** Acreedores varios until refunded | |

### A.8 Nota débito (stage 4) — extends an invoice

| Attribute | Débito | Crédito | Dinámica basis |
|---|---|---|---|
| Client balance | **1305xx** (tercero) | | 1305 (b): "notas débito" |
| Additional revenue | | **41xx** | 41xx |
| Additional IVA | | **2408** / 240805 | |
| Additional retención sufrida | **1355xx** | | |

### A.9 Purchase-side notes (stage 4)

Supplier credit note received: débito **2205** / **2335**, crédito **6225** Devoluciones, rebajas y descuentos en
compras (CR) for goods or the expense account for services, crédito **2408**/240810 for IVA, débito
**2365/2367/2368** for retención reversed (2367 (b), 2368 (b)). Supplier debit note: the mirror.

### A.10 Sub-accounts the seed must create

Official sub-accounts used in this stage: 110505, 111005, 130505, 130510, 135515, 135517, 135518, 220501–220599
(free), 2335xx, 236515, 236520, 236525, 236530, 236540, 236570, 2367, 2368, 240801–240899 (free), 4135xx, 4155xx,
4175, 5105–5195, 6205. Reserved for later stages: 1435, 6135 (inventory), 6225 (purchase notes). Mustang auxiliares: 11050501, 11100501, 13050501, 13051001, 22050501, 240805, 240810, and
one auxiliar per ReteICA municipality under 2368 and 135518.

---

## Technical plan

*Appended when implementation starts: stack, owning contexts, slices, verification plan and, if needed, the
parallel-build split. Deliberately absent from this specification.*
