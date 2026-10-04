# The smoke suite (Playwright)

The simple cases of [`docs/tests/ui-regression.md`](../../docs/tests/ui-regression.md), run by a script against this
checkout's Docker stack. It runs **before** anything is checked by hand in the browser: a failure here is recorded,
fixed and the suite run again until it is green; only then does the manual run of the complex cases start.

```bash
backend/e2e/smoke.sh                  # prepare the stack (reset to the demo seed) and run everything
backend/e2e/smoke.sh access           # one spec file
SMOKE_KEEP_DATA=1 backend/e2e/smoke.sh -g "ACC-03"     # one case, without resetting the stack
```

`prepare.sh` drops and recreates the dev database, signs up the demo company (`app:demo:seed`:
`demo@mustang.test` / `mustang-demo-123`, development only), trusts `X-Forwarded-For` (`backend/.env.dev.local`) and
empties the mail catcher and the rate limits. Reports: `e2e/.results/` (gitignored).

## Conventions

- **One spec per area** (`<area>.spec.ts`), each test named by its case: `test('SAL-03 · …', …)`. In a split, each
  item writes its cases in its own spec file and its own ID range (docs/pdr/prd-accounting.md, Split).
- **In the suite**, a covered case gets a line right under its title: ``Smoke: `e2e/access.spec.ts`.`` or
  ``Smoke (part): `e2e/…` covers …; by hand: ….``
- **Import from `./support/test`**, not from `@playwright/test`: every test is its own visitor for the rate limits,
  and `newCompany()` signs up a company of the test's own (its owner signed in on the page), so no test depends on
  another's data. Read-only checks may use the demo company.
- **Find things as a person does** (role, label, text), never by CSS class, and never wait a fixed time.
