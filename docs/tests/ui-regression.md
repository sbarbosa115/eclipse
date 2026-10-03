# UI regression suite

What a real user does with Mustang, case by case, written as steps and what must happen. It is run in two parts: the
**smoke suite** (Playwright, `backend/e2e/`) runs the simple cases against the Docker stack, and when it is green a
person runs the rest in a browser, **in order**: later cases use the data earlier ones create.

A case with `Smoke:` under its title is run by the script named there and skipped by the manual run; with
`Smoke (part):` the script covers what the line says and the rest ("by hand: …") is run manually; with neither, the
whole case is manual.

Every feature adds its cases here, in the section of the screen it lives on. In the accounting split
([`docs/pdr/prd-accounting.md`](../pdr/prd-accounting.md), Split) each item owns a section and an ID range. Case IDs
are stable: a new case takes the next number in its range, and a removed case leaves a gap.

Each run is recorded as a new file in [`runs/`](runs/) (`python3 ~/.claude/skills/symfony-react-app/scripts/new-run.py`
creates it, `smoke.py` runs the smoke suite and records it).

## Before you start

**Start from known data.** `backend/e2e/prepare.sh` does all of this (the smoke suite runs it first):

```bash
docker compose exec php php bin/console doctrine:database:drop --force
docker compose exec php php bin/console doctrine:database:create
docker compose exec php php bin/console doctrine:migrations:migrate -n
docker compose exec php php bin/console app:demo:seed
```

- `docker compose ps`: every service is up, and `docker compose logs node` says the last build compiled.
- Open Mailpit (http://localhost:8035, or this checkout's `MAILPIT_PORT`) next to the app: cases that send e-mail
  end with "an e-mail arrives".
- Use one browser profile for the run and nothing else on the app's origin in it: tabs share one session.
- Have ready: a small PDF (a supplier's invoice) and a PNG (a company logo).

**Accounts.** Development only, never reuse these passwords.

| Role | Sign in with | Lands on |
|---|---|---|
| Owner of the demo company | `demo@mustang.test` / `mustang-demo-123` | `/` (Tablero) |

Other roles are invited by the owner in the cases of section 1.

**On every screen, whatever the case says**, also check:

- The browser console has no errors, and nothing is a blank page.
- No text shows a raw translation key (`salesInvoice.title`) or English copy.
- Money shows as `$ 1.190.000,00`, dates as `DD/MM/YYYY`.
- Tables, filters and buttons come from the kit and look like the screens next to them.
- A success or error message appears after every save, and it belongs to *that* save.

---

## 1. Access: sign-up, sign-in, users and roles

<!-- Owned by item 0 (ACC-01 – 05) and item 1 "access" (ACC-06 – 19). -->

**ACC-01 · A company signs up and lands on its dashboard**
Smoke: `e2e/access.spec.ts`.
Open `/registro` › Razón social `Prueba Registro S.A.S.`, NIT `901234567`, Tu nombre `Ana Pérez`, correo
`ana@prueba.test`, contraseña `una clave bien larga` › **Crear cuenta**.
**Expected:** the Tablero opens; the sidebar shows `Prueba Registro S.A.S.` and `NIT 901234567-<DV>` (the DV
computed), the role `Administrador`, and every section of the menu.

**ACC-02 · The owner signs in and out**
Smoke: `e2e/access.spec.ts`.
Open `/ingresar` › `demo@mustang.test` / `mustang-demo-123` › **Ingresar** › **Cerrar sesión**.
**Expected:** signing in shows the demo company; signing out shows the sign-in page, and reloading `/` stays there.

**ACC-03 · A wrong password is refused without saying which part is wrong**
Smoke: `e2e/access.spec.ts`.
`/ingresar` with `demo@mustang.test` and `no-es-la-clave`.
**Expected:** "Correo o contraseña incorrectos." The same message for an e-mail that has no account.

**ACC-04 · A link to a section, signed out, signs in first and then opens it**
Smoke: `e2e/access.spec.ts`.
Signed out, open `/facturas-venta` › sign in as the demo owner.
**Expected:** the sign-in page first; after signing in, Facturas de venta opens (not the Tablero).

**ACC-05 · The sign-up form explains what is wrong**
Smoke: `e2e/access.spec.ts`.
`/registro` › NIT `abc`, contraseña `corta` › **Crear cuenta**; then a complete form with an e-mail already
registered.
**Expected:** under NIT "Escribe el NIT solo con números, sin el dígito de verificación.", under Contraseña "La
contraseña debe tener al menos 10 caracteres."; with the taken e-mail, "Este correo ya está registrado." under it.

**ACC-90 · The layout on a tablet and in both themes**
At 1024×768 and at 768×1024, and with Tema `Claro` and `Oscuro`: sign-in, sign-up, the Tablero and Configuración.
**Expected:** nothing overflows sideways; below 1024px the menu is a drawer that opens with ☰ and closes on Escape,
on the backdrop and after choosing a section.

## 2. Company setup and resolution

<!-- Owned by item 2 "company" (CO-01 – 19). -->

## 3. Ledger: chart, posting rules, libro diario, balance de prueba

<!-- Owned by item 3 "ledger" (LED-01 – 29). -->

**LED-01 · A new company has the PUC with its own auxiliares**
Smoke: `e2e/ledger.spec.ts`.
Sign up a company › **Configuración › Plan de cuentas**. The table starts at `1 ACTIVO`, in code order, each level
indented under its parent. Search `1105` › `11050501 CAJA GENERAL` shows *Propia* and level *Auxiliar*. Search
`iva generado` › `240805` is listed.

**LED-02 · The owner adds an auxiliar for a bank account**
Smoke: `e2e/ledger.spec.ts`.
Plan de cuentas › search `111005` › **Agregar subcuenta bajo 111005** (+) › Código `11100502`, Nombre
`Bancolombia ahorros` › **Guardar**. "Cuenta 11100502 creada." and the row appears. Repeat with the same code › the
modal says "Ya existe una cuenta con ese código." and stays open.

**LED-03 · A PUC account keeps its name and can be deactivated**
Smoke: `e2e/ledger.spec.ts`.
Plan de cuentas › search `110510` › **Editar** (lápiz). Nombre is disabled with the hint "El nombre de las cuentas del
PUC no se cambia." › untick *Cuenta activa* › **Guardar**. The row shows *Inactiva*.

**LED-04 · The owner moves revenue to a services account**
Smoke: `e2e/ledger.spec.ts`.
**Configuración › Reglas contables**: *Ingresos por ventas* goes to `413595 VENTA DE OTROS PRODUCTOS`. **Cambiar** ›
the modal says "Solo cuentas que empiezan por 41." › search `415595` › pick `415595 ACTIVIDADES CONEXAS` › **Guardar**.
"Ingresos por ventas ahora va a la cuenta 415595."

**LED-05 · The owner locks the books, never beyond today**
Smoke: `e2e/ledger.spec.ts`.
Reglas contables › *Fecha de bloqueo contable* says the books are open. *Bloquear hasta* a date next year › **Guardar
fecha** › "La fecha de bloqueo no puede ser posterior a hoy." Then `31/01/2026` › "Libros bloqueados hasta el
31/01/2026."; after a reload "Bloqueado hasta el 31/01/2026.".

**LED-06 · A new company opens each book, empty and balanced**
Smoke: `e2e/ledger.spec.ts`.
**Libros contables** opens *Libro diario* (`/contabilidad/diario`) with "Aún no hay asientos…". *Balance de prueba*: "No
hay movimientos en este periodo." *Estado de resultados*: every total `$ 0,00`. *Balance general*: "Activo = pasivo +
patrimonio."

**LED-07 · The libro diario shows each entry with its lines**
Before: `docker compose exec php php bin/console app:ledger:demo-entries` (posts three sample entries for the demo
company, dated this month; until the document items land, the only way entries exist).
Sign in as the demo owner › Libros contables › Libro diario. Three entries, by date: *FE-DEMO-1* (Dr `11050501`
$ 1.190.000,00; Cr `413595` $ 1.000.000,00 and `240805` $ 190.000,00), *FC-DEMO-1* and *RP-DEMO-1*, each with its
*Comprobante de prueba* label, number, débitos and créditos aligned right, a zero side left blank, and a totals line
whose two sides are equal. Type `2408` in the search box › only FE-DEMO-1 and FC-DEMO-1 remain. Clear it › all three.

**LED-08 · The balance de prueba balances and drills down to the diario**
After LED-07. *Balance de prueba*, period this month. "Débitos y créditos cuadran." and the totals line shows the same
amount twice. *Nivel de detalle* `Clase` › only classes 1, 2, 4, 5; `Auxiliar` › every account down to `11050501`.
A crédito balance (`4` INGRESOS) shows negative. On `2408` click **Ver asientos de 2408** › the diario opens filtered
by account `2408` and the same period. Set *Desde* after today › the balance says there is no movement.

**LED-09 · The estado de resultados and the balance general agree**
After LED-07. *Estado de resultados* for this month: Ingresos $ 1.000.000,00 (41 › 4135), Gastos $ 200.000,00
(51 › 5135), Utilidad del periodo $ 800.000,00. *Balance general* at today: "Activo = pasivo + patrimonio." and
*Resultado del ejercicio* $ 800.000,00.

**LED-10 · The settings tabs read well next to their siblings**
Configuración › Plan de cuentas and Reglas contables next to *Impuestos* and *Formas de pago*: same intro line, filter
bar, table and actions column. Every modal (agregar, editar, cambiar cuenta) opens, takes typing and closes with Esc.
Check at laptop and tablet widths, light and dark; no raw `ledger.` key anywhere.

**LED-11 · Only the owner and the accountant keep the books**
After section 1 invites an accountant and a billing user. As the **accountant**: Libros contables opens; the chart and
the posting rules can be changed. As the **billing user**: Libros contables says "Los libros contables son del
administrador y del contador."; Plan de cuentas and Reglas contables are listed with a note that only the owner and the
accountant change them, and show no edit buttons and no *Bloquear hasta* field.

**LED-12 · An emitted document posts to the ledger**
After the sales-invoice item merges (section 8). Emit a factura de venta paid half in cash and half on credit ›
Libro diario shows one entry *Factura de venta* with Dr caja and `13050501` (with the client's name), Cr `413595`
and `240805`, balanced. Void it › a second entry dated the void date, with the sides swapped. Lock the books until
today (LED-05) › emitting a document dated today is refused ("period_locked").

## 4. Taxes and payment methods

<!-- Owned by item 4 "taxes-payments" (TAX-01 – 19). -->

## 5. Terceros

<!-- Owned by item 5 "terceros" (TER-01 – 19). -->

## 6. Products and services

<!-- Owned by item 6 "catalog" (PRD-01 – 19). -->

## 7. The document form

<!-- Owned by item 7 "document-editor" (DOC-01 – 09). -->

## 8. Facturas de venta

<!-- Owned by item 8 "sales-invoice" (SAL-01 – 29). -->

## 9. Facturas de compra / gasto

<!-- Owned by item 9 "purchase-invoice" (PUR-01 – 29). -->

## 10. Cotizaciones

<!-- Owned by item 10 "quotation" (COT-01 – 19). -->

## 11. Recibos de caja

<!-- Owned by item 11 "cash-receipt" (RC-01 – 19). -->

## 12. Recibos de pago / egreso

<!-- Owned by item 12 "supplier-payment" (PAY-01 – 19). -->

## 13. Cartera, reports and dashboard

<!-- Owned by item 13 "reports" (REP-01 – 19). -->
