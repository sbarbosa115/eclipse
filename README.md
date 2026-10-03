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
| GET, POST, PUT | `/products`, `/products/quick`, `/products/{id}/taxes` | contract only: 501 until the "catalog" item | 501 |

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

## Known gaps

- Terceros: *Autocompletar datos* from RUES/DIAN is out of scope (Q19). The per-tercero account pickers offer the accounts the chart search returns for 1305 / 2205 / 2335 (at most 20 each).
- Stage 1 is being built in parallel items; see the split in `docs/pdr/prd-accounting.md`. Until an item merges, its
  section shows "Esta sección se está construyendo." and its endpoints answer 501.
- Taxes: validity dates do not yet filter `GET /taxes` or the document pickers (a document picks any active tax);
  the rate is one per tax, so a change of rate is an edit (documents keep their copy), not a second dated rate.
  Impoconsumo and ReteICA have no seeded account (no standard sub-account in the PUC).
- Out of scope for stage 1 (PRD §2 and the technical plan): inventory, remissions, credit/debit notes, DIAN
  transmission, manual vouchers, saldos iniciales, régimen simple behaviour, UVT thresholds, cuotas, several
  resolutions, RUES autocomplete, Excel export, multi-company users.
