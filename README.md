# Eclipse — Mustang

Mustang is a cloud business-management app for small and medium companies in Colombia, starting from accounting (the
PUC, double-entry journal, terceros, IVA and withholdings). Eclipse is its Symfony + React codebase, hosted on a
cPanel account.

- **What it does:** [`docs/pdr/prd-mustang.md`](docs/pdr/prd-mustang.md) (roadmap) and
  [`docs/pdr/prd-accounting.md`](docs/pdr/prd-accounting.md) (stage 1, with its technical plan and parallel split).

## Running it locally

Everything runs in Docker; nothing is installed on the host.

```bash
docker compose up -d
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate -n
docker compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate --env=test -n
docker compose exec php php bin/console app:demo:seed
```

| What | Where |
|---|---|
| The app | http://localhost:8090 (`demo@mustang.test` / `mustang-demo-123`, development only) |
| E-mails (Mailpit) | http://localhost:8035 |
| MariaDB | `localhost:3316`, user `app` / `app` |

A second checkout (a git worktree for a feature) picks other host ports in its own gitignored `.env`
(`HTTP_PORT`, `DB_PORT`, `MAILPIT_PORT`); `split.py start` writes it.

The `node` service rebuilds the UI on every save: read `docker compose logs node` instead of running a build.

### Tests and checks

```bash
docker compose exec php php bin/phpunit                        # PHP: unit + functional (database app_test)
docker compose exec node npm test                              # Vitest + Testing Library
backend/e2e/smoke.sh                                           # Playwright smoke suite (resets the dev database)
~/.claude/skills/symfony-react-app/scripts/gate.sh --fix       # PHP-CS-Fixer, PHPStan, Deptrac, Prettier, ESLint, tsc
```

The same gate runs in CI (`.github/workflows/ci.yml`). The regression suite is
[`docs/tests/ui-regression.md`](docs/tests/ui-regression.md); each run is recorded in `docs/tests/runs/`. Security
audits are in [`docs/security/`](docs/security/README.md).

After changing a controller or an Output DTO, regenerate the API types the UI imports:

```bash
docker compose exec php php bin/console nelmio:apidoc:dump --format=json > backend/assets/types/openapi.json
docker compose exec node npm run -s api:types
```

## Architecture

**Backend** (`backend/src/`): DDD and hexagonal layers, one folder per bounded context, each with
`Domain / Application / Infrastructure / UI`. Deptrac enforces the direction (`deptrac.yaml`) and which contexts may
talk to each other (`deptrac.contexts.yaml`).

| Context | Owns |
|---|---|
| `Shared` | money (`Money`, `Rate`, `Quantity`, `UnitPrice` on brick/math), document totals (§4.6), the DV algorithm, fiscal enums, posting concepts, error kinds → JSON, command and event buses, **tenancy** (`CompanyOwned` + the Doctrine `company` filter), the shared document shapes (lines, payment lines, open items, allocations), audit log, attachments |
| `Access` | users, sign-up (creates the company), sign-in (json_login + session), roles |
| `Company` | the company, its invoicing resolution, numbering series (`Numbering`, row-locked) |
| `Ledger` | chart of accounts (PUC), posting rules, taxes, payment methods, journal entries, `JournalPoster` (the one way into the books), lock date, ledger reports |
| `Party` | terceros and their contacts |
| `Catalog` | products, services, categories |
| `Sales` | cotización, factura de venta, receivables, recibo de caja |
| `Purchasing` | factura de compra / gasto, payables, recibo de pago |
| `Reporting` | cartera with ageing, exports, the dashboard (read side) |

A new company is provisioned inside the sign-up transaction by every context's `CompanyProvisioner` (chart, posting
rules, taxes, payment methods, numbering series). Every owned row has `company_id`; repositories load by
`(company, id)` and the `company` filter is the second lock. Foreign keys between contexts are declared with
`#[References('table')]` on the id column (`Shared\Infrastructure\Doctrine\ForeignKeys` adds them to the schema).

**Frontend** (`backend/assets/react/`): React 19 + TypeScript (strict), Webpack Encore, Feature-Sliced Design:
`app → pages → widgets → features → entities → shared`. ESLint fails an import that goes up a layer or skips a
slice's `index.ts`. Types come from the OpenAPI schema (`Schema<'TaxOutput'>`). Every string goes through
`useTranslation()`; the Spanish catalog is one file per namespace (`shared/i18n/locales/es/`). The UI kit is
`@/shared/ui` (tables, filters, fields, modals, the theme).

## API reference

All under `/api/v1`, JSON in `snake_case`. Money and rates are decimal strings (`"1190000.00"`, `"19.0000"`). Errors are
`{"error": "<code>", "message": "…", "detail"?: {…}, "violations"?: [{field, message}]}`; another company's id is
404. Writes must come from the app's own origin (403 otherwise).

| Method | Path | Answers | Errors |
|---|---|---|---|
| POST | `/auth/sign-up` | `{company_name, nit, owner_name, email, password ≥ 10}` → 201 `SessionOutput`, signed in. Provisions the company | 422 `validation_failed` (`email` taken, `identification_number` taken); 429 (5 per hour per IP) |
| POST | `/auth/sign-in` | `{email, password}` → `SessionOutput` and a session cookie | 401 `invalid_credentials`; 429 (5 per minute per e-mail and IP) |
| POST | `/auth/sign-out` | ends the session → 204 | 405 for another method |
| GET | `/me` | `SessionOutput {user_id, email, name, role, company_id, company_name, company_nit, company_check_digit}` | 401 |
| GET | `/taxes` | `{items: TaxOutput[]}`; `?class=charge\|withholding`, `?all=1` with inactive | 401 |
| GET | `/payment-methods` | `{items: PaymentMethodOutput[]}`; `?all=1` | 401 |
| GET | `/accounts/search` | `{items: AccountOutput[]}` postable accounts, `?q=` code prefix or name, `?purchases=1` | 401 |
| GET | `/settings/taxes`, `/settings/payment-methods` | `{items: TaxSettingOutput[]}` / `{items: PaymentMethodSettingOutput[]}`: every row, inactive too, with the accounts' codes and names and `in_use` (a document, product or company default points at it). Every role | 401 |
| POST | `/taxes` | `{name, tax_class, kind, calculation, rate, sales_account_id?, purchase_account_id?, valid_from?, valid_to?}` → 201 `TaxSettingOutput`. Owner and accountant | 403; 422 `validation_failed` on `name` (taken), `kind`, `calculation`, `rate` (0–100, four decimals), `valid_to` (before `valid_from`), `*_account_id` |
| PUT | `/taxes/{id}` | `{name, calculation, rate, sales_account_id, purchase_account_id, valid_from, valid_to}` → `TaxSettingOutput`; class and kind never change | 403; 404; 422 as above, `tax_not_editable` (Ninguno) |
| POST | `/taxes/{id}/deactivate`, `/activate` | → `TaxSettingOutput` | 403; 404; 422 `tax_not_editable` |
| DELETE | `/taxes/{id}` | → 204; a tax no document uses | 403; 404; 409 `tax_in_use`; 422 `tax_not_editable` |
| POST | `/payment-methods` | `{name, kind: cash\|credit, account_id?}` → 201 `PaymentMethodSettingOutput`; contado needs a postable account, crédito none. Owner and accountant | 403; 422 on `name` (taken), `kind`, `account_id` |
| PUT | `/payment-methods/{id}` | `{name, account_id}` → `PaymentMethodSettingOutput`; the kind never changes | 403; 404; 422 |
| POST | `/payment-methods/{id}/deactivate`, `/activate` | → `PaymentMethodSettingOutput` | 403; 404 |
| DELETE | `/payment-methods/{id}` | → 204; a method no document uses | 403; 404; 409 `payment_method_in_use` |
| GET | `/terceros` | `{items: TerceroSummaryOutput[], total, page, per_page}`; `?q=` part of the name, trade name or identification (`%`/`_` literal), `?role=cliente\|proveedor\|empleado\|otro`, `?active=1\|0`, `?page`, `?per_page ≤ 100` | 401 |
| POST | `/terceros` | full `TerceroInput` → 201 `TerceroOutput` (phones, billing data, responsabilidades, roles, contacts, account overrides). DV computed for a NIT when `check_digit` is empty | 422 `validation_failed` (field), 422 `duplicate_identification` (violation on `identification_number`), 403 accountant |
| POST | `/terceros/quick` | `{person_type, identification_type, identification_number, check_digit?, first_names?, last_names?, business_name?, email, roles}` → 201 `TerceroSummaryOutput` | 422 as above, 403 |
| GET, PUT | `/terceros/{id}` | `TerceroOutput`; PUT replaces the whole record (contacts with an `id` are kept, the rest removed) | 404 other company, 422 `tercero_erased`, 403 (PUT, accountant) |
| DELETE | `/terceros/{id}` | 204 when no document names it | 409 `tercero_in_use`, 403 |
| POST | `/terceros/{id}/deactivate`, `/reactivate` | `TerceroOutput` | 404, 403, 422 `tercero_erased` (reactivate) |
| GET | `/terceros/{id}/contacts` | `{items: ContactOutput[]}` | 404 |
| GET | `/terceros/{id}/export` | Ley 1581: `{exported_at, tercero}` as an attachment; audited (`tercero.personal_data_exported`) | 404, 403 (billing and owner only) |
| POST | `/terceros/{id}/erase` | Ley 1581: blanks the personal fields and contacts, deactivates, sets `erased_at`; keeps the row and the identification; audited | 404, 403 |
| GET | `/accounts` | page of `AccountOutput` (the chart in code order): `?q=` digits a code prefix, words the name; `?class=1…9`; `?page`, `?per_page` ≤ 100 | 401 |
| POST | `/accounts` | `{parent_code, code (parent + 2 digits), name, usable_on_purchases?}` → 201 `AccountOutput`; owner and accountant | 403; 409 `account_code_taken`; 422 `account_code_invalid`, `parent_account_not_found` |
| PUT | `/accounts/{id}` | `{name, active, usable_on_purchases}` → `AccountOutput`; owner and accountant; audited | 403; 404; 409 `account_standard` (PUC names are fixed), `account_in_posting_rule` |
| GET | `/posting-rules` | `{items: PostingRuleOutput[]}` (concept, account, `allowed_prefixes`) in the order of §5 | 401 |
| PUT | `/posting-rules/{concept}` | `{account_id}` → `PostingRuleOutput`; owner and accountant; audited (`posting_rule.changed`) | 403; 404; 409 `account_not_postable`; 422 `account_not_allowed_for_concept` |
| GET, PUT | `/ledger/lock-date` | `LockDateOutput {locked_until}`; PUT `{locked_until: YYYY-MM-DD}` by owner and accountant, audited | 403; 422 `lock_date_in_future` |
| GET | `/ledger/journal` | page of `JournalEntryOutput` (with lines, account and tercero names): `?from`, `?to`, `?account` (code and children), `?tercero_id`, `?page`, `?per_page` ≤ 100; owner and accountant | 400 `invalid_date`; 403 |
| GET | `/ledger/trial-balance` | `TrialBalanceOutput`: per account and parent, opening/débito/crédito/closing (débito − crédito), totals, `balanced`; `?from`, `?to` (default the year so far) | 400; 403 |
| GET | `/ledger/income-statement` | `IncomeStatementOutput` (classes 4, 6, 7, 5 by group and cuenta; net income) for `?from`–`?to` | 400; 403 |
| GET | `/ledger/balance-sheet` | `BalanceSheetOutput` (classes 1–3, current earnings, `balanced`) at `?date` | 400; 403 |
| GET, POST | `/terceros`, `/terceros/quick`, `/terceros/{id}/contacts` | contract only: 501 until the "terceros" item | 501 |
| GET | `/products` | `{items: ProductOutput[], total, page, per_page}` by name; `?q=` (código or name, matched literally), `?type=producto\|servicio`, `?active=1` only active / `0` only inactive, `?page`, `?per_page` ≤ 100. Every role | 401 |
| GET | `/products/units` | `{items: {code, name}[]}`: the short DIAN list (94, KGM, MTR, HUR, ZZ) | 401 |
| GET | `/products/{id}` | `ProductOutput` (`unit_price_net_of_tax` is the value a line starts from; `*_account_label` is "code · name") | 404 |
| POST | `/products` | full form `{type, code, name, description?, category_id?, unit_code?, sale_price, price_includes_tax, charge_tax_id?, withholding_tax_id?, revenue_account_id?, expense_account_id?}` → 201. Taxes missing = the company's defaults; unit missing = 94 (producto) / ZZ (servicio) | 422 on `code` (taken in this company), on a tax (not an active tax of that class), an account (not postable), `category_id`, `sale_price` (under a per-unit tax it includes); 403 accountant |
| POST | `/products/quick` | `{type, code, name, sale_price, price_includes_tax, charge_tax_id?, withholding_tax_id?}` → 201 `ProductOutput` | as above |
| PUT | `/products/{id}` | the full form again; what is missing is **none** (no default is filled) | 404, 422, 403 |
| PUT | `/products/{id}/taxes` | `{charge_tax_id, withholding_tax_id}`, null = no tax ("use these taxes from now on") | 404, 422, 403 |
| POST | `/products/{id}/deactivate`, `/reactivate` | `ProductOutput`; inactive products leave new documents' pickers (`?active=1`) | 404, 403 |
| DELETE | `/products/{id}` | 204 when no document line uses it | 409 `product_in_use` (deactivate instead), 404, 403 |
| GET, POST, PUT | `/product-categories`, `/product-categories/{id}` | flat list by name `{items: {id, name, product_count}[]}`; create `{name}` → 201; rename `{name}` | 422 on `name` (repeated), 404, 403 |
| GET, PUT | `/company` | `CompanyOutput {id, legal_name, trade_name, identification_type, identification_number, check_digit, address, city, phone, email, logo_id, vat_regime, fiscal_responsibilities, default_charge_tax_id, default_withholding_tax_id}`. PUT replaces the profile (owner); the DV is computed for a NIT when `check_digit` is empty, and kept when given; dots in a NIT are dropped. Every role reads | 403 (PUT, not owner); 422 on `identification_number` (another company's), `identification_type`, `check_digit`, `email`, `vat_regime`, `fiscal_responsibilities[i]`, `default_charge_tax_id` / `default_withholding_tax_id` (not an active tax of that class) |
| POST | `/company/logo` | multipart field `file`: PNG or JPEG judged by content, ≤ 2 MB (owner) → `CompanyOutput`. Replaces the previous logo (its file is deleted); audited | 403; 413 `logo_too_large`; 415 `logo_unsupported` (also a missing file) |
| GET, DELETE | `/company/logo` | GET: the image (every role). DELETE: removes it (owner) → 204; audited | 404 `logo_not_found` |
| GET | `/company/resolution` | `ResolutionSettingsOutput {resolution: ResolutionOutput\|null, status: ResolutionStatusOutput, manual_invoicing_confirmed_at}`; `resolution` carries `next_number` (consecutivo actual) and `has_issued_numbers`. Every role | 401 |
| GET | `/company/resolution/status` | `{status: missing\|not_yet_valid\|active\|expired\|exhausted, numbers_left, days_left, warning, warning_numbers, warning_days}`; `warning` is true only while active and under either threshold. Every role | 401 |
| POST, PUT | `/company/resolution` | `{resolution_number, prefix, range_from, range_to, valid_from, valid_to, mode: electronic\|manual}` → `ResolutionSettingsOutput` (201 on POST). Owner. One per company; once invoices were numbered, desde and the prefix cannot change and hasta cannot go below the last number used | 403; 409 `resolution_exists` (POST); 404 `resolution_not_found` (PUT); 422 on `range_to` (below desde / the last used), `valid_to`, `range_from`, `prefix`, `mode` (manual before the confirmation) |
| PUT | `/company/resolution/warnings` | `{warning_numbers, warning_days}` → `ResolutionSettingsOutput`; audited. Owner | 403; 422 |
| POST | `/company/manual-invoicing-confirmation` | the owner confirms the company holds the DIAN permission → `ResolutionSettingsOutput`; stored on the company (`manualInvoicingConfirmedBy/At`) and audited; unlocks `mode: manual` | 403 |
| GET | `/company/numbering` | `{items: {kind, prefix, next_number}[]}`: quotation, cash_receipt, purchase_invoice, supplier_payment, sales_invoice_internal (not the journal's). Every role | 401 |
| PUT | `/company/numbering/{kind}` | `{prefix (≤ 10 letters/digits, kept in capitals), next_number}` → the series; audited. Owner | 403; 404 `numbering_series_not_found` (journal_entry, unknown); 422 on `next_number` (below the current one), `prefix` |

## Data model decisions

- **Ids are UUID v7, stored BINARY(16).** The tenancy filter compares `company_id` to `UNHEX(...)`.
- **Terceros:** tipo + número (+ código de sucursal) is unique per company; dots and dashes are not part of the number. Roles are four flags (any combination). A tercero a document names (any table with `tercero_id`, found in the schema) is deactivated, never deleted. Erasing (Ley 1581) keeps the row and the identification (invoices carry it) and blanks everything else; an erased tercero cannot be edited or reactivated. Writes: owner and billing; the accountant reads (§9 Q23).
- **Documents copy what they used** (tax name, rate, accounts; tercero name) so an edit never changes an emitted
  document.
- **Taxes and payment methods are seeded by a `CompanyProvisioner` (priority 50)** that looks accounts up with
  `LedgerCatalog::accountIdByCode()`; a code the chart lacks leaves the account null and posting falls back to the
  kind's posting rule (Impoconsumo 249505, ReteIVA practicada 236701 and the purchase side of Impoconsumo are null until
  the chart has them). "Ninguno" is seeded once per class and is fixed: not editable, deactivated or deleted.
- **A tax's class and kind, and a payment method's kind, never change** after creation (they decide how documents post
  them); the rest is editable and every change is written to `audit_log` (`tax.created|updated|activated|deactivated|
  deleted`, `payment_method.…`) by `Ledger\Application\Port\CatalogAudit`.
- **"In use" is a query** (`CatalogUsage`, DBAL) over the line/payment/receipt tables, products and the company's default
  taxes; a table that starts pointing at a tax or method must be added to `DbalCatalogUsage`.
- **Validity dates** (`valid_from`, `valid_to`, both included, either open) are stored and shown; `Tax::isValidOn()` is
  the rule. Nothing filters by them yet: document pickers will (see Known gaps).
- **Line amounts add up to the document totals to the cent** (largest remainder after one rounding per total), so a
  journal entry built from lines always balances.
- **A product's price may include IVA.** `unit_price_net_of_tax` is the price divided by `1 + rate/100` (rounded half up
  to four decimals), or minus the value for a per-unit tax (`Catalog\Domain\Pricing\PriceNetOfTax`): a line of one unit
  then totals the list price after the document's single rounding. The list price itself is kept as typed.
- **The unidad de medida list lives in one place** (`Catalog\Domain\Model\UnitOfMeasure`): 94 unidad, KGM kilogramo, MTR
  metro, HUR hora and ZZ servicio (DIAN's "mutuamente definido"); the full DIAN list arrives with stage 4.
- **A product used by a document line is never deleted**, only deactivated (`Catalog\Application\Port\ProductUsage`
  asks the line tables by SQL, so Catalog names no Sales or Purchasing class). Catalog reads the company's default taxes
  from `Company\Application\Query\Companies` (a CompanyApi dependency in `deptrac.contexts.yaml`).
- **Catalog writes are checked in the controller** (`Catalog\UI\Http\CatalogAccess`): owner and billing write, the
  accountant reads. The "access" item's voters may replace it.
- **The PUC seed** (`Ledger/Infrastructure/Seed/puc.csv`, 2 519 accounts to subcuenta) was extracted from the Decreto's
  PDF (`docs/references/extract-puc.py`; corrections where the PDF is incomplete are listed in its docstring). Every
  company gets it in one batch of multi-row INSERTs inside the sign-up (≈70–170 ms), plus Mustang's accounts under
  their official parents: the auxiliares 11050501, 11100501, 13050501, 13051001, 22050501 and the first subcuenta of
  each free range a concept needs (220505, 236701, 236801, 240805, 240810, 249505, 417501, 620501).
- **Posting rules point at postable accounts**, so §5's 4-digit defaults resolve to: ingreso 413595 (4135, goods),
  descuento 417501, impoconsumo 249505, retefuente practicada 236570 (the fallback: taxes carry their own subcuenta
  by concept), reteIVA/reteICA practicada 236701/236801, gasto por defecto 519595, compra de mercancías 620501. A rule
  may only move within its concept's part of the PUC (`ConceptAccounts`: ingreso 41, clientes 13, proveedores 22/23…).
- **Balances are débito − crédito** in the balance de prueba (a crédito balance is negative). The balance general
  shows the unclosed result of classes 4–7 as *resultado del ejercicio* inside patrimonio: stage 1 has no closing
  entries.
- **Company settings are the owner's** (`Company\UI\Http\Controller\EditsCompany`); accountant and billing read. Every change is written to `audit_log` through `Company\Application\Port\CompanyAudit` (`company.updated|logo_changed|logo_removed|manual_invoicing_confirmed|resolution_warnings_updated`, `resolution.created|updated`, `numbering_series.updated`), with from/to.
- **The logo is a Shared `Attachment`** (owner type `company_logo`, owner id the company) stored under `UPLOADS_DIR/<company>/<attachment id>` by `Company\Infrastructure\Storage\FilesystemCompanyLogos`; the controller checks it by content (`finfo` + `getimagesize`: PNG/JPEG only, never SVG) and size (≤ 2 MB) and hands the handler a path. Replacing or removing deletes the old file and row.
- **The invoicing resolution's status is computed on Colombian calendar days** (`America/Bogota`, both ends included): not yet valid, active, expired (after `valid_to`) or exhausted (`next_number > range_to`), in that order. `warning` = active and fewer numbers than `resolution_warning_numbers` (default 100) or fewer days than `resolution_warning_days` (default 30) remain; days left is 0 on the last valid day.
- **`SalesInvoiceNumbering`** (`ResolutionSalesInvoiceNumbering`) locks the resolution row (`SELECT … FOR UPDATE`), refuses with `resolution_missing`, `resolution_inactive` (the *invoice's* date is outside the dates) or `resolution_exhausted` (all `Refused`, 422), and takes the internal consecutive in the same transaction: a rollback gives both numbers back. It needs an open transaction (the command bus opens it) or Doctrine throws.
- **A resolution's hasta may equal the last number used** (it is then exhausted); it cannot go below it. A resolution that has numbered invoices cannot change desde or prefix, so renewing under a new range/prefix is not possible with the single resolution of §9 Q12 (see Known gaps).
- **Manual mode** is rejected on a resolution until the owner confirms the DIAN permission (`POST /company/manual-invoicing-confirmation`); the confirmation is permanent and audited, and going back to electronic is always allowed.
- **The internal series are edited under the same row lock a document takes**; the next number never goes below the current one and the journal's own series (`journal_entry`) is not editable.

## Known gaps

- Terceros: *Autocompletar datos* from RUES/DIAN is out of scope (Q19). The per-tercero account pickers offer the accounts the chart search returns for 1305 / 2205 / 2335 (at most 20 each).
- Stage 1 is being built in parallel items; see the split in `docs/pdr/prd-accounting.md`. Until an item merges, its
  section shows "Esta sección se está construyendo." and its endpoints answer 501.
- Taxes: validity dates do not yet filter `GET /taxes` or the document pickers (a document picks any active tax);
  the rate is one per tax, so a change of rate is an edit (documents keep their copy), not a second dated rate.
  Impoconsumo and ReteICA have no seeded account (no standard sub-account in the PUC).
- Ledger: no ReteICA auxiliares per municipality yet (A.10: created when the company defines its municipalities);
  `app:ledger:demo-entries` posts sample entries for local review until the document items post real ones.
- Company: only one invoicing resolution per company (Q12). Once invoices were numbered from it, desde and the prefix are locked, so a renewal whose range restarts or whose prefix changes cannot be entered yet (it would need a second resolution); extending hasta and the dates works. The logo is not yet printed on PDFs (the document items read `GET /company/logo` / `Companies::view()->logoId`).
- Out of scope for stage 1 (PRD §2 and the technical plan): inventory, remissions, credit/debit notes, DIAN
  transmission, manual vouchers, saldos iniciales, régimen simple behaviour, UVT thresholds, cuotas, several
  resolutions, RUES autocomplete, Excel export, multi-company users.
