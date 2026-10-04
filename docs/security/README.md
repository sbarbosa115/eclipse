# Security audit: process and checklist

Every feature gets a basic security audit **after development and static analysis, before browser verification**
(`docs/feature-development.md` §6). It is not a penetration test. It is a disciplined pass over what the feature
changed, checking for the attacks that hit Symfony + React applications most often, and it leaves a written
record.

- **This file:** the process and the checklist. Change it when you learn a new way things break.
- **`audits/<YYYY-MM-DD>-<feature>.md`:** one file per audit, with every finding and its outcome.

## Process

1. **Scope.** From `git diff origin/main...`, list everything an attacker can reach or influence: new or changed
   routes and their parameters, request bodies, uploads, content rendered back to users (HTML, emails, PDFs), new
   queue messages and console commands, calls to third parties, new dependencies, new env vars or secrets.
2. **Tools.**
   ```bash
   docker compose exec php composer audit                  # PHP dependencies with known CVEs
   docker compose exec node npm audit --omit=dev           # shipped JS dependencies with known CVEs
   docker compose exec php php bin/console debug:router    # the new routes, their methods and paths
   docker compose exec php php bin/console debug:firewall  # the firewall and access control covering them
   git diff origin/main... | grep -nEi "password|secret|api[_-]?key|token|BEGIN .*PRIVATE"
   ```
   If a secret has ever been committed, rotate it: removing it from the history is not enough.
3. **Checklist.** Go through every section below for each item in the scope. Mark each one *checked, nothing
   found*, *finding*, or *not applicable* (with the reason).
4. **Try it.** Where a check can be tried against the local stack (curl, the browser, a functional test), try
   it, don't just reason about it: another tenant's id, the wrong role, a payload in every text field, a spoofed
   upload.
5. **Record** the audit file (template below).
6. **Fix and move on.** For each finding: a failing test that reproduces it → the fix → the test passes → re-run
   static analysis → mark it *fixed* with the commit. Critical and high findings block the merge. A finding outside
   the feature's reach is recorded as *open*, added to the README's "Known gaps", and raised with the user.

### Severity

| Severity | Meaning | Example |
|---|---|---|
| Critical | Remote, unauthenticated, reads or changes other users' data or runs code | SQL injection on a public endpoint; upload that executes PHP |
| High | An authenticated user crosses a boundary | Tenant A reads tenant B's records by id (IDOR); a role reaches another's actions |
| Medium | Needs user interaction or a weak spot that helps a bigger attack | Stored XSS in an admin-only field; no rate limit on a code sign-in |
| Low | Defence in depth, information leak with little value | Missing security header; stack trace on a 500 in a non-prod env |

## Checklist (OWASP Top 10, applied to Symfony + React)

### A01 Broken access control (the most common real finding)

- [ ] Every new route sits behind the right firewall and has an explicit role (`#[IsGranted]`, `access_control`
      or a voter). Public routes are public on purpose.
- [ ] **IDOR:** every load by id goes through the ownership/tenant filter. Another tenant's id returns **404** (not
      403, not the data). A functional test proves it.
- [ ] Ownership is never taken from the request body: the owner, tenant and author come from the session.
- [ ] Lists, exports, counts and search results are scoped as tightly as single reads, including via joins.
- [ ] Hidden buttons are not security: the API refuses the action for the role the UI hides it from.
- [ ] Files are downloaded through a controller that checks access, never from a guessable public URL.

### A02 Cryptographic failures

- [ ] Passwords are hashed by Symfony's `auto` hasher. Tokens and codes come from `random_bytes()` /
      `Uuid::v4()`, never `rand()` or `uniqid()`.
- [ ] Sign-in codes, reset tokens and invitations are single-use, expire, and are compared in constant time
      (`hash_equals`).
- [ ] No secrets in the repository: they live in env vars, `.env.local` or Symfony secrets. `.env` holds only
      dev defaults.
- [ ] Sensitive data is not logged (passwords, tokens, full ID numbers, card data).

### A03 Injection

- [ ] **SQL/DQL:** every value is a bound parameter (`setParameter`). No string concatenation into DQL, SQL,
      `ORDER BY` or `LIMIT`. A sort or column name from the request is checked against an allow-list.
- [ ] `LIKE` searches escape `%` and `_`.
- [ ] **XSS (React):** no `dangerouslySetInnerHTML` with user content, unless it is sanitised (DOMPurify) and the
      reason is commented. No user-controlled `href` without checking the scheme (`javascript:`).
- [ ] **XSS (Twig/email):** no `|raw` on user content. Rich text is sanitised on input with
      `symfony/html-sanitizer`.
- [ ] **Command injection:** no `exec`/`shell_exec`/`proc_open` with user input. Use `Process` with an argument
      array.
- [ ] **Header/email injection:** user input in email headers or subjects goes through Mailer's API, never string
      building.

### A04 Insecure design

- [ ] Rate limits (`symfony/rate-limiter`) on sign-in, code and password-reset endpoints, invitations, and
      anything that sends email/SMS or costs money.
- [ ] State transitions are enforced by the domain (you cannot "pay" a cancelled invoice by calling the endpoint
      directly).
- [ ] Mass assignment: Input DTOs list exactly the fields a user may set. Entities are never deserialised straight
      from the request.

### A05 Security misconfiguration

- [ ] `APP_ENV=prod` and `APP_DEBUG=0` in production. The profiler and API doc bundles are dev-only.
- [ ] Error responses do not leak stack traces, SQL or paths.
- [ ] Security headers on responses: `Content-Security-Policy`, `X-Content-Type-Options: nosniff`,
      `Referrer-Policy`, `frame-ancestors`/`X-Frame-Options`, and HSTS in production.
- [ ] CORS allows only the origins that need it (`nelmio/cors-bundle`), never `*` with credentials.
- [ ] Cookies holding sessions/refresh tokens are `HttpOnly`, `Secure`, and `SameSite=Lax` or `Strict`.

### A06 Vulnerable and outdated components

- [ ] `composer audit` and `npm audit --omit=dev` are clean, or every remaining advisory is recorded with why it
      does not apply.
- [ ] New dependencies are maintained, widely used, and pinned by the lock file.

### A07 Identification and authentication failures

- [ ] JWTs have a short lifetime and a refresh flow. Signing out revokes the refresh token.
- [ ] Sign-in errors do not reveal whether an account exists ("wrong email or password").
- [ ] Session/token is renewed on sign-in and on privilege change (impersonation, role change).
- [ ] CSRF: cookie-authenticated form posts carry a CSRF token. A JWT sent in an `Authorization` header is not
      exposed to CSRF, but a refresh endpoint that reads a cookie is: it needs `SameSite` and an origin check.

### A08 Software and data integrity failures

- [ ] **Uploads:** the MIME type is checked from the content (Symfony `File` constraint), not the extension, and
      sizes are limited. Files are stored outside the web root with generated names and served with
      `Content-Disposition` and `nosniff`. SVG and HTML are refused, or served as attachments.
- [ ] **Path traversal:** no user input in a filesystem path without normalising it and checking it stays inside
      the storage root.
- [ ] Deserialisation: no `unserialize()` on user input. Messenger messages hold ids, not objects from the client.

### A09 Security logging and monitoring failures

- [ ] Failed sign-ins, refused access (403) on sensitive actions, and admin/impersonation actions are logged with
      who, what and when, and without secrets.

### A10 Server-side request forgery (SSRF)

- [ ] No server-side fetch of a URL the user supplies (webhooks, "import from URL", image by URL) without an
      allow-list of hosts and a block on private/internal IP ranges.

### Frontend specifics

- [ ] No secrets in the bundle: anything in the React code or `process.env` at build time is public.
- [ ] Tokens in `localStorage` are readable by any XSS. So the XSS checks above are what protects the session,
      and CSP matters.
- [ ] Open redirects: a `?redirect=`/`?next=` value is only followed if it is a relative path on the same origin.
- [ ] `target="_blank"` links to user-supplied URLs carry `rel="noopener noreferrer"`.

## Audit file template

`audits/<YYYY-MM-DD>-<feature>.md`:

```markdown
# Security audit — <feature> — <date>

- **Branch:** `feature/<name>` at `<commit>`
- **Scope:** routes, inputs, uploads, rendered content, jobs, dependencies this feature added or changed.
- **Tools:** `composer audit` (result), `npm audit --omit=dev` (result), secrets grep (result).

## Findings

| # | Severity | Category | Where | What an attacker could do | Status |
|---|---|---|---|---|---|
| 1 | High | A01 IDOR | `GET /api/x/{id}` | Read another tenant's X by guessing ids | Fixed in `<commit>`, test `testAnotherTenantGets404` |
| 2 | Low | A05 Headers | nginx | No CSP | Open: known gap, raised with the user |

## Checked, nothing found

A01 (routes and roles, list scoping), A03 (DQL parameters, no `dangerouslySetInnerHTML`), …

## Not applicable

A10: the feature makes no outbound request.
```

A feature with no findings still gets its audit file: *"nothing found"* is a result, and it records that the
checks were done.
