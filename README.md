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
| GET, POST, PUT | `/products`, `/products/quick`, `/products/{id}/taxes` | contract only: 501 until the "catalog" item | 501 |

## Data model decisions

- **Ids are UUID v7, stored BINARY(16).** The tenancy filter compares `company_id` to `UNHEX(...)`.
- **Documents copy what they used** (tax name, rate, accounts; tercero name) so an edit never changes an emitted
  document.
- **Line amounts add up to the document totals to the cent** (largest remainder after one rounding per total), so a
  journal entry built from lines always balances.
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

## Known gaps

- Stage 1 is being built in parallel items; see the split in `docs/pdr/prd-accounting.md`. Until an item merges, its
  section shows "Esta sección se está construyendo." and its endpoints answer 501.
- Ledger: no ReteICA auxiliares per municipality yet (A.10: created when the company defines its municipalities);
  `app:ledger:demo-entries` posts sample entries for local review until the document items post real ones.
- Out of scope for stage 1 (PRD §2 and the technical plan): inventory, remissions, credit/debit notes, DIAN
  transmission, manual vouchers, saldos iniciales, régimen simple behaviour, UVT thresholds, cuotas, several
  resolutions, RUES autocomplete, Excel export, multi-company users.
