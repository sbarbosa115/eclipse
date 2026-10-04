# Working on Eclipse (Mustang)

Colombian accounting SaaS on Symfony + React, hosted on cPanel. Read `README.md` first (architecture, API reference,
decisions, known gaps) and `docs/pdr/prd-accounting.md` (stage 1: the spec, the technical plan and the split).

Features are built with the `symfony-react-app` skill: plan, a worktree per feature, test-first, the gate
(`gate.sh`), a security audit, the browser, a regression run, a PR.

## Running things

Everything runs in Docker; there is no PHP or Node on the host. `docker compose exec php php bin/console …`,
`docker compose exec php php bin/phpunit`, `docker compose exec node npm test`. The `node` service rebuilds the UI on
every save: check `docker compose logs node`.

## Rules that are easy to break

- **Money is never a float.** `Shared\Domain\Money` (brick/math) in PHP; decimal strings on the wire; the UI formats
  them for display only (`formatMoney`) and never does arithmetic that is posted.
- **Every owned entity implements `CompanyOwned`** and has `company_id`. Load by `(company, id)`; another company's
  id answers 404. Prove it in each endpoint's tests.
- **Only `JournalPoster` writes the books.** Documents post concepts (`Shared\Domain\Accounting\PostingConcept`) or
  explicit accounts; never write `journal_entry` rows elsewhere.
- **Documents are immutable once emitted**: change through a void (reversing entry), never an edit.
- **Production is cPanel**: PHP 8.4 + MariaDB, no always-on process, no Redis. The e-mail queue is Messenger's
  Doctrine transport drained by a cron line (`deploy/cpanel-update.sh`); a new env var goes in
  `deploy/env.local.example`.
- **The API is `snake_case`** (the serializer's name converter); PHP stays camelCase.
- **Spanish (Colombia) only, through `t()`**: copy lives in `shared/i18n/locales/es/<namespace>.json`, money as
  `$ 1.190.000,00`, dates `DD/MM/YYYY`. Code identifiers in English.
- **Screens are built only from `@/shared/ui`** (the kit): tables with an Actions column last, `FilterBar`,
  `Field`, `FormModal`, `EmptyState`. Check every screen at laptop and tablet widths, light and dark.
- **Prettier formats YAML too**: write Symfony tags in block form (`!tagged_iterator` on its own line, keys below).
- **Babel parses `.ts` as TSX:** write `function f<T>()`, never a generic arrow `<T>() =>`.
