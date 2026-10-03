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

## Data model decisions

- **Ids are UUID v7, stored BINARY(16).** The tenancy filter compares `company_id` to `UNHEX(...)`.
- **Documents copy what they used** (tax name, rate, accounts; tercero name) so an edit never changes an emitted
  document.
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

## Known gaps

- Stage 1 is being built in parallel items; see the split in `docs/pdr/prd-accounting.md`. Until an item merges, its
  section shows "Esta sección se está construyendo." and its endpoints answer 501.
- Out of scope for stage 1 (PRD §2 and the technical plan): inventory, remissions, credit/debit notes, DIAN
  transmission, manual vouchers, saldos iniciales, régimen simple behaviour, UVT thresholds, cuotas, several
  resolutions, RUES autocomplete, Excel export, multi-company users.
