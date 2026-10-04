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

Configuración › **Usuarios** (`/configuracion?tab=users`) is the owner's. Invitations and password resets arrive in
Mailpit; open each link in a **private window** (or another browser), as the invitee would, so the owner's session
stays where it is.

**ACC-06 · The owner sees the Usuarios tab with themself in it**
Smoke: `e2e/users.spec.ts`.
As the demo owner › Configuración › **Usuarios**.
**Expected:** one row: `Dueña Demo`, `demo@mustang.test`, the badge `Tú`, rol `Administrador`, its last sign-in as
`DD/MM/YYYY HH:MM`; the actions are **Cambiar rol** only (nobody deactivates themselves); **Invitar usuario** and
**Invitar a tu contador** above the table.

**ACC-07 · An invited billing user accepts by e-mail and lands signed in**
Smoke: `e2e/users.spec.ts`.
**Invitar usuario** › correo `luis@demo.test`, Rol `Facturación` › **Enviar invitación**; in Mailpit open the
e-mail "Comercializadora Demo S.A.S. te invita a Mustang" › **Aceptar la invitación** › Tu nombre `Luis Gómez`,
contraseña `una clave bien larga` › **Entrar a la empresa**.
**Expected:** "Enviamos la invitación a luis@demo.test."; the row shows `Sin nombre aún`, tinted as invited, and
"Invitación vigente hasta el <today + 7>". The link opens "Únete a Comercializadora Demo S.A.S." with the role and
e-mail; after accepting, the Tablero opens as Luis (`Facturación` in the sidebar, no Configuración in the menu), and
the address bar shows `/` (the token is gone). Reloading the owner's Usuarios tab shows `Luis Gómez`, active.

**ACC-08 · "Invitar a tu contador" invites an accountant**
Smoke: `e2e/users.spec.ts`.
**Invitar a tu contador** › correo `contadora@demo.test` › **Enviar invitación**; accept as `Eva Ruiz`.
**Expected:** the modal is titled "Invitar a tu contador" with Rol `Contador` chosen and what the accountant may do
under it; Eva lands on the Tablero as `Contador`, sees Configuración in the menu, and its Usuarios tab says "Solo el
administrador de la empresa gestiona los usuarios."

**ACC-09 · The invitation form explains what is wrong**
Smoke: `e2e/users.spec.ts`.
**Invitar usuario** › correo `demo@mustang.test` › **Enviar invitación**; then `no-es-correo`.
**Expected:** "Este correo ya está registrado." under Correo electrónico (an e-mail registered in any company is
refused); then "Escribe un correo válido." without calling the server. The modal stays open.

**ACC-10 · Resending an invitation replaces the earlier link**
Smoke: `e2e/users.spec.ts`.
Invite `reenvio@demo.test`; on its row **Reenviar invitación**; open the **first** e-mail's link, then the second's.
**Expected:** "Enviamos de nuevo la invitación a reenvio@demo.test. El enlace anterior ya no sirve."; the first
link shows "Este enlace ya no sirve" with **Ir a ingresar**; the second one works.

**ACC-11 · The owner changes a role; the last owner keeps theirs**
Smoke: `e2e/users.spec.ts`.
Luis's row › **Cambiar rol** › `Contador` › **Cambiar rol**; then the owner's own row › `Facturación`.
**Expected:** "Luis Gómez ahora tiene el rol Contador." and the row says `Contador`; for the owner, the modal shows
"La empresa debe tener al menos un administrador activo." and nothing changes.

**ACC-12 · The Usuarios tab and its modals on a tablet and in both themes**
At 1024×768 and 768×1024, Tema `Claro` and `Oscuro`: the Usuarios tab with an active, an invited and a deactivated
user, the invite modal, Cambiar rol and the deactivation confirmation; the three public pages (`/invitacion`,
`/recuperar-contrasena`, `/restablecer-contrasena`).
**Expected:** nothing overflows sideways; the legend explains the invited and deactivated tints, and each tinted row
also says its status to a screen reader (title on hover); the two invite buttons wrap under the intro rather than
squeeze it; the public pages look like the sign-in page.

**ACC-13 · A deactivated user is signed out and cannot sign in; reactivated, they can**
Smoke: `e2e/users.spec.ts`.
With Luis signed in on another browser, the owner › Luis's row › **Desactivar** › confirm; Luis reloads; Luis tries
to sign in; the owner › **Reactivar**; Luis signs in.
**Expected:** "Luis Gómez ya no puede ingresar. Su sesión se cerró."; Luis's browser shows the sign-in page, and
signing in says "Correo o contraseña incorrectos."; after "Luis Gómez puede ingresar de nuevo." he signs in.

**ACC-14 · A changed role takes effect at once**
With Luis (`Facturación`) signed in on another browser, the owner makes him `Contador`; Luis clicks any section.
**Expected:** Luis's session ends (sign-in page); signed in again, his sidebar says `Contador` and the menu shows
Configuración. Nothing in Luis's open tab keeps working with the old role.

**ACC-15 · A forgotten password is reset by e-mail, and every other session ends**
Smoke: `e2e/users.spec.ts`.
Signed in as the owner in one browser; in another, `/ingresar` › **¿Olvidaste tu contraseña?** › the owner's e-mail
› **Enviar enlace**; open the e-mail "Restablece tu contraseña de Mustang" › Contraseña nueva `una clave nueva y
larga` › **Guardar y entrar**; then go back to the first browser and open any section.
**Expected:** "Si … tiene una cuenta en Mustang, te llegará un correo…"; the link opens "Elige una contraseña
nueva"; saving lands on the Tablero, signed in; the first browser shows the sign-in page, and the old password is
refused there.

**ACC-16 · A reset link works once; an unknown e-mail gets the same answer**
Smoke: `e2e/users.spec.ts`.
**¿Olvidaste tu contraseña?** with `nadie@demo.test`; then reset the owner's password and open the same link again.
**Expected:** the same message for the unknown e-mail (and no e-mail in Mailpit); the used link shows "Este enlace ya
no sirve" with **Pedir otro enlace**, which opens the request page.

**ACC-17 · The two e-mails read well**
In Mailpit, the invitation and the password-reset e-mails, HTML and text parts.
**Expected:** Spanish, no raw template text; the invitation names the company, who invites, the role and the expiry
date (`DD/MM/YYYY`); the reset says the link lasts one hour and that other sessions will close; the button and the
plain link both open this stack's address with the token after `#`. A reset link opened after an hour shows "Este
enlace ya no sirve" (PHPUnit `PasswordResetApiTest` proves the hour with the clock).

**ACC-18 · A session idle for two hours ends**
PHPUnit `SessionExpiryTest` proves the rule with the clock. By hand: sign in, leave the tab alone for two hours, then
click Terceros.
**Expected:** the sign-in page; after signing in, the app works again. A tab used at least once every two hours stays
signed in.

**ACC-19 · Only the owner manages users**
Smoke: `e2e/users.spec.ts`.
Signed in as a billing user, `GET /api/v1/users`; as the accountant, Configuración › Usuarios.
**Expected:** 403 for the billing user (who has no Configuración in the menu); the accountant reads "Solo el
administrador de la empresa gestiona los usuarios." and no list.

**ACC-90 · The layout on a tablet and in both themes**
At 1024×768 and at 768×1024, and with Tema `Claro` and `Oscuro`: sign-in, sign-up, the Tablero and Configuración.
**Expected:** nothing overflows sideways; below 1024px the menu is a drawer that opens with ☰ and closes on Escape,
on the backdrop and after choosing a section.

## 2. Company setup and resolution

<!-- Owned by item 2 "company" (CO-01 – 19). -->

Configuración › **Empresa** (`/configuracion?tab=company`) and **Resolución** (`?tab=resolution`). The owner edits; the
accountant and billing read. CO-10 (the other roles) needs the Usuarios tab (item "access"); CO-18 and CO-19 need
emitted sales invoices (item "sales-invoice"): they are run by hand once those are merged.

**CO-01 · The profile shows what the sign-up gave, with its DV**
Smoke: `e2e/company.spec.ts`.
Sign up a company › Configuración › **Empresa**.
**Expected:** Razón social and NIT as typed at sign-up, Tipo de documento `NIT`, a DV of one digit; no console errors.

**CO-02 · The owner edits the profile and it is kept**
Smoke: `e2e/company.spec.ts`.
Change Razón social, Nombre comercial, Dirección, Ciudad, Teléfono, Correo › **Guardar cambios** › reload.
**Expected:** "Guardamos los datos de la empresa."; after the reload every field shows what was saved.

**CO-03 · The DV is computed when empty and may be typed**
Smoke: `e2e/company.spec.ts`.
Empty the DV › **Guardar cambios**; then type `5` › **Guardar cambios**.
**Expected:** the first save fills the DV with the DIAN's digit; the second keeps `5`.

**CO-04 · A NIT another company has is refused**
Smoke: `e2e/company.spec.ts`.
In company B, type company A's NIT › **Guardar cambios**.
**Expected:** "Ya hay una empresa registrada con este NIT." under the field; nothing is saved. Saving B's own NIT again is fine.

**CO-05 · A document type without a DV hides it**
Smoke: `e2e/company.spec.ts`.
Tipo de documento `Cédula de ciudadanía`, then `NIT`.
**Expected:** the DV field disappears for the cédula and comes back for the NIT.

**CO-06 · Régimen and responsabilidades fiscales are kept**
Smoke: `e2e/company.spec.ts`.
Régimen `Régimen simple de tributación`, tick O-13 and O-15 › **Guardar cambios** › reload.
**Expected:** the same choices after the reload; O-23 stays unticked.

**CO-07 · Default taxes come from the active taxes of their class**
Smoke: `e2e/company.spec.ts`.
Open the two Impuestos por defecto lists; choose `IVA 5 %` and `ReteFuente servicios 4 %` › **Guardar cambios** › reload. In
Impuestos deactivate the one chosen as default and come back.
**Expected:** Impuesto cargo offers IVA/impoconsumo only and Impuesto de retención offers retenciones only; the choices
are kept; a deactivated default is still shown as the current choice, but a deactivated tax is not offered when none is chosen.

**CO-08 · The logo is uploaded, replaced and removed**
Smoke: `e2e/company.spec.ts`.
**Subir logo** › a PNG; **Cambiar logo** › another; **Quitar logo**.
**Expected:** "Guardamos el logo." and the image shows (and loads from `/api/v1/company/logo`); the replacement shows the
new one; "Quitamos el logo." and "Todavía no has subido un logo."; no console errors.

**CO-09 · The logo must be a PNG or JPEG of 2 MB at most, judged by its content**
Smoke: `e2e/company.spec.ts`.
Try an SVG, a PNG over 2 MB, and a text file renamed `logo.png`.
**Expected:** "El logo debe ser una imagen PNG o JPG." for the SVG and the renamed file (the latter is refused by the
server), "El logo pesa más de 2 MB." for the big one; no logo is saved.

**CO-10 · The accountant and billing read, only the owner writes** *(by hand)*
Sign in as an accountant, then as billing › Empresa and Resolución.
**Expected:** every field is disabled, there is no Guardar, Subir logo or Editar, "Solo el propietario puede cambiar…" is
shown; the resolution's warning is still visible. In the API a PUT answers 403.

**CO-11 · The owner sets the resolution up and sees it in force**
Smoke: `e2e/company.spec.ts`.
Resolución › número `18760000001`, prefijo `SETP`, desde `1`, hasta `1000`, fechas of today ± months › **Crear resolución**.
**Expected:** "Creamos la resolución."; the banner reads "Tu resolución está vigente. Te quedan 1000 números y … días."; the
Consecutivo actual is `1`; it is still there after a reload.

**CO-12 · The resolution form explains what is wrong**
Smoke: `e2e/company.spec.ts`.
**Crear resolución** empty; then hasta `10` below desde `500`; then a Fecha de fin before the start.
**Expected:** "Este dato es obligatorio." on the empty fields; Hasta and Fecha de fin are marked invalid; nothing is saved.

**CO-13 · Manual mode waits for the owner to confirm the DIAN permission**
Smoke: `e2e/company.spec.ts`.
Modalidad: `Manual` is disabled › **Confirmar permiso de la DIAN** › **Confirmo que la empresa tiene el permiso** › choose
`Manual (talonario)` › **Crear resolución**.
**Expected:** the modal explains that sales invoices are electronic by law; after confirming, Manual is selectable and
the date of the confirmation is shown; the resolution is saved as manual. The confirmation is in the audit log.

**CO-14 · The owner is warned when few numbers are left**
Smoke: `e2e/company.spec.ts`.
Create a resolution with desde `1`, hasta `50`.
**Expected:** a yellow warning "Tu resolución se está agotando: te quedan 50 números y … días…" at the top of the tab.

**CO-15 · The warning thresholds are editable**
Smoke: `e2e/company.spec.ts`.
In CO-14's company set Avisar con menos de `10` números and `5` días › **Guardar avisos**.
**Expected:** "Guardamos los avisos."; the yellow warning gives way to the green "Tu resolución está vigente…".

**CO-16 · An expired resolution is shown as blocking**
Smoke: `e2e/company.spec.ts`.
Create a resolution whose Fecha de fin was yesterday.
**Expected:** a red banner "Tu resolución venció: no puedes emitir facturas de venta hasta que la actualices."; a resolution
whose Fecha de inicio is in the future reads "todavía no está vigente: empieza el …".

**CO-17 · The internal numbering is edited and never goes back**
Smoke: `e2e/company.spec.ts`.
Numeración interna › Recibo de caja › **Editar** › prefijo `rcb`, próximo número `250` › **Guardar**; edit again with `100`.
**Expected:** the row reads `RCB` and `250`; the second edit shows "El próximo número no puede ser menor que 250, el actual."
and changes nothing. The journal's own series is not listed.

**CO-18 · Once invoices are numbered, desde and the prefix are locked** *(by hand, after "sales-invoice" is merged)*
Emit a factura de venta, then open Resolución.
**Expected:** Prefijo and Desde are disabled with the explanation; Hasta can grow but a value below the last number used
is refused (422 on Hasta); the Consecutivo actual is the next number.

**CO-19 · Emission is refused outside the resolution** *(by hand, after "sales-invoice" is merged)*
Emit with a fecha before the resolution's start or after its end; then set hasta equal to the last number used and emit again.
**Expected:** the first is refused with `resolution_inactive`, the second with `resolution_exhausted`; in both the
invoice stays a draft and no number or internal consecutive was consumed.

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

Configuración › **Impuestos** (`/configuracion?tab=taxes`) and **Formas de pago** (`?tab=paymentMethods`). The owner and
the accountant edit; every role reads. Cases TAX-11, 12, 13 and 16 need the chart of accounts (item "ledger") or another
item's documents, so they are run by hand once those are merged.

**TAX-01 · A new company has the seeded taxes**
Smoke: `e2e/taxes.spec.ts`.
Sign up a new company › Configuración › **Impuestos**.
**Expected:** IVA 19 %, IVA 5 %, IVA 0 %, IVA por servicios 19 %, Impoconsumo 8 %, Impoconsumo por valor, ReteFuente
servicios 4 %, ReteFuente compras 2,5 %, ReteFuente honorarios 10 % and 11 %, ReteIVA 15 % and two **Ninguno** rows;
rates read `19 %`, `2,5 %`; no ReteICA; no console errors.

**TAX-02 · The class filter narrows the list**
Smoke: `e2e/taxes.spec.ts`.
Impuestos › Clase `Retenciones`, then `Impuestos`.
**Expected:** only retenciones (ReteIVA 15 %…) in the first, only impuestos cargo (IVA 19 %…) in the second.

**TAX-03 · The accountant creates a tax**
Smoke: `e2e/taxes.spec.ts`.
**Nuevo impuesto** › Nombre `IVA 16 %`, Tarifa `16`, Vigente desde `01/01/2027` › **Guardar**.
**Expected:** "Impuesto creado."; the row shows `16 %` and `Desde 01/01/2027`.

**TAX-04 · A retención by municipality takes a decimal comma**
Smoke: `e2e/taxes.spec.ts`.
**Nuevo impuesto** › Nombre `ReteICA Bogotá 0,966 %`, Clase `Retención`, Tipo `ReteICA`, Tarifa `0,966`.
**Expected:** the row reads `Retención · ReteICA` and `0,966 %`.

**TAX-05 · Impoconsumo may be a value per unit**
Smoke: `e2e/taxes.spec.ts`.
**Nuevo impuesto** › Tipo `Impoconsumo`, Cálculo `Valor por unidad`, valor `500`.
**Expected:** the row reads `$ 500,00`. For IVA the Cálculo choice is not offered.

**TAX-06 · The form explains what is wrong**
Smoke: `e2e/taxes.spec.ts`.
**Nuevo impuesto** › **Guardar** empty; then Vigente desde `01/02/2027` and hasta `01/01/2027`; then the name of an
existing tax in other case (`iva 19 %`).
**Expected:** "Este campo es obligatorio." and "Escribe un número, con hasta cuatro decimales."; "La fecha final no puede
ser anterior a la inicial."; "Ya hay un impuesto con este nombre." under Nombre. The form stays open with its data.

**TAX-07 · Editing a tax changes its rate and dates, not its kind**
Smoke: `e2e/taxes.spec.ts`.
IVA 5 % › **Editar** › Tarifa `6`, Vigente hasta `31/12/2030` › **Guardar**.
**Expected:** the form shows the class and kind as text (they cannot be chosen); "Impuesto actualizado."; the row reads
`6 %` and `Hasta 31/12/2030`.

**TAX-08 · A tax is deactivated and activated again**
Smoke: `e2e/taxes.spec.ts`.
IVA 5 % › **Desactivar** › **Activar**.
**Expected:** "«IVA 5 %» ya no se ofrece en los documentos."; the row stays, tinted, with the legend `Inactivo`; then
"«IVA 5 %» se ofrece de nuevo en los documentos." and the tint goes. By hand: while inactive, IVA 5 % is not offered in
a document's tax picker (after the document editor merges).

**TAX-09 · An unused tax is deleted after a confirmation**
Smoke: `e2e/taxes.spec.ts`.
Create `IVA 16 %` › **Eliminar** › **Cancelar**; **Eliminar** › **Eliminar**.
**Expected:** Cancel keeps it; confirming shows "«IVA 16 %» eliminado." and the row is gone.

**TAX-10 · "Ninguno" has no actions**
Smoke: `e2e/taxes.spec.ts`.
**Expected:** the two Ninguno rows have no Editar, Desactivar or Eliminar.

**TAX-11 · A tax posts to the accounts the accountant chooses**
Needs the chart (item "ledger"). Impuestos › IVA 19 % › **Editar** › Cuenta en ventas: type `240805`.
**Expected:** the box completes to `240805 · …` as the code is recognised, the list offers matches by code or name,
only postable accounts; text that is not an account shows "Elige una cuenta de la lista." and does not save; saved, the
table shows the code. On a new company the seeded IVA, ReteFuente and ReteIVA rows already show their codes
(240805/240810, 135515/2365xx, 135517).

**TAX-12 · A tax or payment method a document used cannot be deleted**
After the invoice items merge: emit a factura de venta using IVA 19 % and Efectivo. Then Impuestos and Formas de pago.
**Expected:** IVA 19 % and Efectivo have **Editar** and **Desactivar** but no **Eliminar**; deactivating them works and
the emitted invoice still shows them unchanged. An unused one still offers **Eliminar**.

**TAX-13 · A billing user only reads**
After the "access" item merges: invite a user with role Facturación, sign in as them, open both tabs.
**Expected:** both lists show, with no "Nuevo…" button, no Acciones column.

**TAX-14 · A new company has the seeded payment methods**
Smoke: `e2e/taxes.spec.ts`.
Formas de pago on a new company.
**Expected:** Efectivo, Tarjeta débito, Tarjeta crédito, Transferencia (Contado) and Crédito (cuenta del tercero); no
console errors. By hand, with the chart: Efectivo reads `11050501 · Caja general`, the other three `11100501 · Bancos…`.

**TAX-15 · A crédito method needs no account**
Smoke: `e2e/taxes.spec.ts`.
**Nueva forma de pago** › Nombre `Crédito a 90 días`, Tipo `Crédito`.
**Expected:** the Cuenta field disappears and a note says it posts to the tercero's account; "Forma de pago creada.".

**TAX-16 · A contado method is created on a postable account**
Needs the chart. **Nueva forma de pago** › Nombre `Bancolombia`, Tipo `Contado`, Cuenta `11100502`.
**Expected:** the account completes to its full label; saved, the row shows it; a group account such as `1110` is not
offered (only subcuentas and auxiliares). Editing it can move the account and rename it, never change Contado/Crédito.

**TAX-17 · A contado method asks for its account**
Smoke: `e2e/taxes.spec.ts`.
**Nueva forma de pago** › Nombre `Bancolombia` › **Guardar**; then Cuenta `texto que no es una cuenta`.
**Expected:** "Elige la cuenta donde entra el dinero."; then "Elige una cuenta de la lista."; nothing is saved.

**TAX-18 · A method is deactivated, activated and deleted**
Smoke: `e2e/taxes.spec.ts`.
Create `Crédito a 90 días` (crédito) › **Desactivar** › **Activar** › **Eliminar** › **Eliminar**.
**Expected:** the row is tinted `Inactiva`, then normal; "«Crédito a 90 días» eliminada." and it is gone.

**TAX-19 · The layout on a tablet and in both themes**
Impuestos and Formas de pago with the form modal open, at 1024×768 and 768×1024, Tema `Claro` and `Oscuro`.
**Expected:** nothing overflows sideways (the table scrolls inside its box); the modal's fields stack in one column on
a narrow screen; the inactive tint and the action colours are readable in both themes.

## 5. Terceros

<!-- Owned by item 5 "terceros" (TER-01 – 19). -->

**TER-01 · The first tercero is a company, with its DV computed**
Smoke: `e2e/terceros.spec.ts`.
Sign up a new company › **Terceros** (empty: "Aún no tienes terceros…") › **Crear tercero** › Número `800197268`
(under it "Calculado: 4") › Razón social `Distribuciones Andina S.A.S.` › role Cliente › **Agregar teléfono** `6011234567`
› **Agregar contacto** `Pedro Ruiz` › **Crear tercero**.
**Expected:** back on the list with "Tercero Distribuciones Andina S.A.S. creado."; the row shows `NIT 800197268-4` and
the badge Cliente.

**TER-02 · A person has nombres and a cédula, with no DV**
Smoke: `e2e/terceros.spec.ts`.
**Nuevo tercero** › Tipo `Persona`, Tipo de identificación `Cédula de ciudadanía`.
**Expected:** the DV field disappears and Nombres / Apellidos replace Razón social. Saving `Ana María` `Pérez Soto`,
`1020304050`, Empleado lists `Ana María Pérez Soto`, `CC 1020304050`, Empleado.

**TER-03 · The same identification twice is refused on its field**
Smoke: `e2e/terceros.spec.ts`.
Create a company with NIT `800197268`, then another with `800.197.268` (dots are ignored).
**Expected:** "Ya existe un tercero con esta identificación." under Número de identificación; nothing was created. The
same NIT with Código de sucursal `1` is accepted.

**TER-04 · The form says what is missing before saving**
Smoke: `e2e/terceros.spec.ts`.
**Nuevo tercero** › **Crear tercero** with nothing filled; then a wrong e-mail and a DV of two digits.
**Expected:** "Este campo es obligatorio." under the number and the Razón social, "Elige al menos un rol.", "Escribe un
correo válido.", "El DV es un solo dígito."; no request is sent until they are fixed.

**TER-05 · The list searches, filters and offers a way back**
Smoke: `e2e/terceros.spec.ts`.
With two terceros (a cliente and an otro): filter Rol `Otro`; clear it and search `800.197`; search `zzz`;
**Ver todo**.
**Expected:** each filter narrows the rows (and the address keeps it: reload keeps the filters); `zzz` shows "Ningún
tercero coincide con tu búsqueda." with **Ver todo**, which clears every filter.

**TER-06 · A tercero is edited and the change is kept**
Smoke: `e2e/terceros.spec.ts`.
**Editar** › Ciudad `Medellín`, DV `9` (replacing the computed one) › **Guardar tercero** › reload.
**Expected:** "Cambios guardados."; after the reload the city and the DV are as saved.

**TER-07 · A tercero is deactivated and activated again**
Smoke: `e2e/terceros.spec.ts`.
**Desactivar** › confirm › filter Estado `Activos` › Estado `Inactivos` › **Activar**.
**Expected:** the inactive row is tinted and the legend says Inactivo; it leaves Activos and shows in Inactivos;
**Activar** brings it back with no confirmation.

**TER-08 · A tercero no document uses is deleted after confirming**
Smoke: `e2e/terceros.spec.ts`.
**Eliminar** › read the dialog › **Eliminar**.
**Expected:** the dialog names the tercero; after confirming it is gone and the empty state shows.

**TER-09 · Personal data is erased on request and the row stays**
Smoke: `e2e/terceros.spec.ts`.
Create a person with e-mail › **Editar** › **Suprimir datos personales** › confirm.
**Expected:** a notice says the data was erased on that date; the form is read-only with no Guardar; the list shows
`Datos suprimidos` with the same `CC` number, no e-mail, inactive.

**TER-10 · The personal data is exported as JSON**
Create a tercero with phone, contact and e-mail › **Editar** › **Exportar datos (JSON)**.
**Expected:** a `tercero-<número>.json` file downloads with every field of the tercero (phones, contacts, fiscal
responsibilities) and the export date. Doing it leaves an entry in the audit log (`tercero.personal_data_exported`).

**TER-11 · The accounting accounts of a tercero**
Needs the chart of the "ledger" item. **Editar** › Cuentas contables.
**Expected:** Cuenta por cobrar lists only 1305xx accounts and Cuenta por pagar only 2205xx / 2335xx; the first option
is "Predeterminada…". Saving a choice keeps it after reload; choosing the default again clears it.

**TER-12 · A tercero a document uses cannot be deleted, only deactivated**
Needs a document (cotización or factura) for a tercero. **Eliminar** on that tercero › confirm.
**Expected:** the dialog stays open with "Hay documentos con este tercero: no se puede eliminar, solo desactivar."
**Desactivar** works, and the document still shows the tercero's name.

**TER-13 · Quick-create from a document**
Needs the document form. In a new factura de venta, open "Crear tercero" in the client picker, create `Cliente Rápido
S.A.S.` with NIT, correo, role Cliente (already checked).
**Expected:** the modal closes and the new tercero is selected in the document; it also appears in Terceros. A NIT
already used is refused in the modal, next to the number.

**TER-14 · The accountant reads and cannot change**
Sign in as an accountant (invited in section 1). Open Terceros and a tercero.
**Expected:** no Nuevo tercero, Desactivar, Eliminar or Suprimir; the row action is **Ver** and the form's fields are
disabled with no Guardar. Calling the API to write answers 403.

**TER-15 · A billing user creates and edits terceros**
Sign in as a billing user. Create, edit, deactivate and delete one.
**Expected:** every action works.

**TER-16 · Another company's terceros do not exist**
With two companies, copy the address of a tercero of company A (`/terceros/<id>`), sign in as company B and open it.
**Expected:** the screen says the tercero could not be loaded (404); B's list never shows A's rows or counts.

**TER-17 · The search treats % and _ as plain characters**
Create `Banco 100% Fiable`. Search `100%`, then `%`, then `_`.
**Expected:** `100%` finds it; `%` and `_` alone find nothing (they are not wildcards).

**TER-18 · Pagination of a long list**
With more than 25 terceros (create them, or from the API), open Terceros.
**Expected:** 25 rows per page, "Página 1 de N", **Siguiente** / **Anterior** work and keep the filters; the page is in
the address.

**TER-19 · The layout on a tablet and in both themes**
At 1024×768 and 768×1024, Tema `Claro` and `Oscuro`: the list, the form (all sections, with phones and contacts
added) and the confirm dialogs.
**Expected:** nothing overflows sideways; phone and contact rows stack at narrow widths; the Guardar bar stays
reachable; inactive rows are still distinguishable (tint plus the legend).

## 6. Products and services

<!-- Owned by item 6 "catalog" (PRD-01 – 19). -->

Open **Productos y servicios** (`/productos`). Cases PRD-12 onwards need the taxes, the chart and the document forms of the
other items: run them after those are merged.

**PRD-01 · A new company sees what the section is for and how to start**
Smoke: `e2e/products.spec.ts`.
A company with no products › open Productos y servicios.
**Expected:** "Aún no tienes productos ni servicios. Créalos aquí o desde una línea de factura." with the button **Nuevo
producto o servicio**; no table, no error.

**PRD-02 · The full form creates a product that shows in the list**
Smoke: `e2e/products.spec.ts`.
**Nuevo producto o servicio** › Código `CUA-01`, Nombre `Cuaderno rayado`, Descripción larga, Precio `4500` › **Guardar**.
**Expected:** "Producto creado."; the row shows `CUA-01`, `Cuaderno rayado`, Producto, Unidad, `$ 4.500,00`.

**PRD-03 · A código already used is refused on its field**
Smoke: `e2e/products.spec.ts`.
Create a second product with the same Código (any case of letters).
**Expected:** under Código "Ya hay un producto o servicio con este código."; the form stays open with what was typed.

**PRD-04 · The form explains what is missing or wrong**
Smoke: `e2e/products.spec.ts`.
**Guardar** an empty form; then Precio `12,5`.
**Expected:** "Este campo es obligatorio." under Código, Nombre and Precio; then "Escribe el precio en pesos, con
máximo cuatro decimales." under Precio. Nothing is saved.

**PRD-05 · A service starts with the unit "servicio"**
Smoke: `e2e/products.spec.ts`.
In a new product, Unidad shows `94 · Unidad`; change Tipo to Servicio.
**Expected:** Unidad becomes `ZZ · Servicio`; choosing `HUR · Hora` and saving shows Servicio and Hora in the row. The
list of units is only 94, KGM, MTR, HUR, ZZ.

**PRD-06 · Editing a product changes it**
Smoke: `e2e/products.spec.ts`.
**Editar** a product › change Nombre and Precio `12500.5` › **Guardar**.
**Expected:** "Producto guardado."; the row shows the new name and `$ 12.500,50`; the form had opened with the saved
values (Precio `10000`, not `10000.0000`).

**PRD-07 · Search and filters narrow the list, and "Ver todo" brings it back**
Smoke: `e2e/products.spec.ts`.
Search `arr`; clear it and filter Tipo `Servicio`; search `zzz`; **Ver todo**.
**Expected:** each narrows the table; with no match "Ningún producto o servicio coincide con lo que buscas." and **Ver
todo**, which clears the search and every filter. By hand: a `%` in the search matches a literal `%`; the address keeps
the filters (`?q=…&type=…`) and reloading keeps them.

**PRD-08 · Deactivating and reactivating**
Smoke: `e2e/products.spec.ts`.
**Desactivar** a product › filter Estado `Activos`, then `Inactivos` › **Reactivar**.
**Expected:** "«…» quedó inactivo: no aparece en documentos nuevos."; it leaves Activos and shows in Inactivos with the
row tinted and read as "Inactivo"; reactivating says "«…» está activo otra vez."

**PRD-09 · A product no document used can be deleted, after confirming**
Smoke: `e2e/products.spec.ts`.
**Eliminar** › **Cancelar**; then **Eliminar** › **Eliminar**.
**Expected:** cancelling changes nothing; confirming says "«…» se eliminó." and the row is gone.

**PRD-10 · Categories: add, rename, no repeated name, and use one on a product**
Smoke: `e2e/products.spec.ts`.
**Categorías** › add `Papelería`; add it again; **Renombrar** it `Útiles escolares` › close › create a product in that
category.
**Expected:** the list is flat and by name; the repeated name shows "Ya hay una categoría con este nombre." under the
field; the product row shows the category under its name; the Categorías dialog shows how many products each has.

**PRD-11 · A long list is paged**
Smoke: `e2e/products.spec.ts`.
With 22 products.
**Expected:** "Página 1 de 2" with 20 rows; **Siguiente** shows "Página 2 de 2" with 2.

**PRD-12 · A price that includes IVA carries the net value for the line**
Needs the seeded taxes (item "taxes-payments").
**Nuevo producto o servicio** › Precio `119000`, check **Incluir IVA en el precio**, Impuesto cargo `IVA 19 %` ›
**Guardar**; then a second product at `50000` with the same options.
**Expected:** the first row reads `$ 119.000,00` and "IVA incluido · base $ 100.000,00"; the second "base $ 42.016,81"
(shown to the cent; the line carries 42016.8067). Both produce a one-unit invoice line totalling `119.000,00` and
`50.000,00` (checked again in the document cases). A tax per unit (impoconsumo por valor) is taken off as a value.

**PRD-13 · A new product takes the company's default taxes**
Needs Configuración › Empresa with default taxes.
Set the default Impuesto cargo to `IVA 19 %` and Impuesto retención to `ReteFuente servicios 4 %` › create a product
leaving both selects on "El de la empresa" › **Editar** it.
**Expected:** the edit form shows `IVA 19 %` and `ReteFuente servicios 4 %` selected. Choosing "Sin impuesto" on edit
and saving clears them (an edit writes what it shows). Only active taxes of the right class are listed.

**PRD-14 · Revenue and expense accounts are picked from the chart**
Needs the PUC seed (item "ledger").
In the form type `4135` in Cuenta de ingreso › pick one `413595 · …` from the suggestions › save › edit.
**Expected:** the account shows by code and name when editing; typing text that is not one of the suggestions shows
"Elige una cuenta de la lista o borra el campo."; emptying the field saves no account (the posting rules apply).

**PRD-15 · Quick-create from a document line**
Needs the document form (items "document-editor" and "sales-invoice").
In a new factura de venta, on a line type a name that does not exist › **Crear** (quick-create) › Tipo, Código, Precio
`119000` with **Incluir IVA en el precio** › **Crear**.
**Expected:** the modal closes and the line is that product: its description, the company's default taxes, and a Valor
unitario of `100.000,00` (net of IVA) so that the line's total is `119.000,00`; the product is in Productos y servicios.

**PRD-16 · Quick-create refuses what the full form refuses**
Needs the document form.
From a line, quick-create with a Código already used and then with an empty Nombre.
**Expected:** the reasons show under their fields inside the modal ("Ya hay un producto o servicio con este código."),
the document and the line are untouched, and Escape or **Cancelar** closes it.

**PRD-17 · A product used by a document is only deactivated**
Needs an emitted factura de venta with the product.
**Eliminar** the product › **Eliminar**.
**Expected:** "«…» ya está en documentos: no se puede eliminar. Desactívalo…" with **Desactivar**; choosing it leaves the
product inactive. A new document no longer offers it; the emitted document still shows it.

**PRD-18 · The accountant only reads**
Needs an invited accountant (item "access").
Sign in as the accountant › open Productos y servicios › **Ver** a product › **Categorías**.
**Expected:** no **Nuevo producto o servicio**, **Desactivar** or **Eliminar**; **Ver** opens the form with every field
disabled and only **Cerrar**; Categorías shows the list with no way to add or rename. A billing user sees and does it all.

**PRD-19 · The layout on a tablet and in both themes**
At 1024×768 and 768×1024, Tema `Claro` and `Oscuro`: the list, the form, the Categorías dialog and the quick-create
modal.
**Expected:** nothing overflows sideways (the table scrolls inside its box); the form's two columns become one on a
narrow screen; the inactive row is distinguishable in both themes; the console has no errors.

## 7. The document form

<!-- Owned by item 7 "document-editor" (DOC-01 – 10). -->

The form a cotización, factura de venta and factura de compra share. Until those pages exist (items 8–10) it is tried on
its development page, **`/dev/editor-documento`** (not routed in a production build): the **Documento** select switches
between Factura de venta, Factura de compra and Cotización, **Comprobar** checks the draft as emission would, and
**Ver como emitido** shows it read-only. Nothing is saved. Once the document pages mount it, run these cases there too.

**DOC-01 · The totals preview shows the PRD example as the server computes it**
Smoke (part): `e2e/documentEditor.spec.ts` runs the PRD example; by hand: the three `0.3333` lines.
A service `SRV-01` of 1.000.000 with IVA 19 % and ReteFuente servicios 4 % › on line 1 choose it, Cantidad `2`,
% Descuento `10`.
**Expected:** Total bruto `$ 2.000.000,00`, Descuentos `$ 200.000,00`, Subtotal `$ 1.800.000,00`, Impuestos
`$ 342.000,00`, Retenciones `$ 72.000,00`, Total neto `$ 2.070.000,00`; the line's Valor total `$ 2.142.000,00` (base
plus IVA). Then three lines of Cantidad `1`, Valor unitario `0.3333`, IVA 19 %: Total bruto `$ 1,00`, Impuestos `$ 0,19`
(rounded once, not per line).

**DOC-02 · The tercero is searched from the third character and brings its contacts**
Smoke (part): `e2e/documentEditor.spec.ts`; by hand: choosing another client empties Contacto.
A client with the contact `Ana Pérez` › type two letters of its name in **Cliente**, then a third.
**Expected:** with two, "Escribe al menos 3 caracteres." and no list; with three, the client with its NIT; choosing it
fills the box and **Contacto** offers `Ana Pérez`. Choosing another client empties Contacto.

**DOC-03 · A missing tercero is created from the search with the document's role**
Smoke: `e2e/documentEditor.spec.ts`.
Type a name nobody has › **+ Crear nuevo** › fill NIT, Razón social, correo › **Crear tercero**; then on Factura de
compra, **Proveedor** › **+ Crear nuevo**.
**Expected:** on a sale the dialog opens with *Cliente* ticked, and the new client is chosen in the form when it
closes; on a purchase *Proveedor* is ticked.

**DOC-04 · A product is created from a line and fills it**
Smoke: `e2e/documentEditor.spec.ts`.
On line 1 type `Cuaderno rayado` › **+ Crear nuevo** › Código `CUA-01`, Precio `11900`, *Incluir IVA en el precio*,
IVA 19 % › **Crear**.
**Expected:** the dialog starts with Nombre `Cuaderno rayado`; the line shows `CUA-01 · Cuaderno rayado`, Cantidad
`1`, Valor unitario `10.000` (net of IVA) and Valor total `$ 11.900,00` (the list price).

**DOC-05 · The tax dialog changes a line and, when asked, the product from now on**
Smoke (part): `e2e/documentEditor.spec.ts`; by hand: the product in Productos y servicios, and without the tick.
Line with `SRV-01` › **Impuestos de la línea 1** › Impuesto cargo `IVA 5 %`, tick *Aplicar estos impuestos al producto
de ahora en adelante* › **Aplicar**; add a line with `SRV-01` again.
**Expected:** the dialog shows the line's subtotal (`$ 1.000.000,00`); Impuestos becomes `$ 50.000,00`; the new line
comes with IVA 5 %, and so does the product in Productos y servicios. Without the tick only the line changes.

**DOC-06 · A purchase line goes straight to an expense account**
Needs the chart of accounts (item "ledger").
Documento `FC · Factura de compra` › **Tipo de línea 1** `Cuenta de gasto` › type `5135` in the line and pick
`513525 · …` from the list; Cantidad `1`, Valor unitario `500000`, IVA 19 %.
**Expected:** the list offers only accounts usable on purchases (classes 5, 6, 7 and those the accountant marked); the
line totals `$ 595.000,00`; **Comprobar** asks nothing more of it. Typing an account that is not in the list and
**Comprobar** says "Elige una cuenta de la lista." under it; switching back to Producto clears the account.

**DOC-07 · Formas de pago add up to Total neto, with a due date on crédito**
Smoke (part): `e2e/documentEditor.spec.ts`; by hand: *Otra fecha* and moving Fecha de elaboración.
A line totalling `$ 1.190.000,00` › **Agregar forma de pago** › Efectivo; change Valor to `595000`; **Comprobar**;
**Agregar forma de pago** › Crédito; then Plazo `Otra fecha` and a date; then Documento `C · Cotización`.
**Expected:** the first row offers `1.190.000` and the check mark "Coincide con el total neto" shows; at `595000`
"Faltan $ 595.000,00 para el total neto" and **Comprobar** says "Total formas de pago ($ 595.000,00) debe ser igual al
total neto ($ 1.190.000,00)."; the crédito row offers the rest, *A 30 días* and its date 30 days after Fecha de
elaboración, and the check mark is back; *Otra fecha* shows a date field; changing Fecha de elaboración moves the
computed dates but not the one typed. A quotation has no Formas de pago.

**DOC-08 · The lines work from the keyboard**
Smoke (part): `e2e/documentEditor.spec.ts`; by hand: the arrows, Enter and Escape in a product search.
Tab through line 1 to % Descuento › Enter; type in Descripción of line 2 › Alt + ↑; then **Quitar línea 1**. In a
product search: ↓, ↑, Enter, Escape.
**Expected:** Enter on the last line adds one and puts the cursor in its Producto/Servicio; Enter on another line goes
down to the same cell; Alt + ↑/↓ moves the line and the cursor with it; in the search the arrows walk the list, Enter
chooses, Escape closes it, and Enter in a cell never submits anything.

**DOC-09 · The form on a tablet, in both themes, and read-only**
At 1366×768, 1024×768 and 768×1024, Tema `Claro` and `Oscuro`, with three lines, two formas de pago and an attachment;
then **Ver como emitido**.
**Expected:** at 1366×768 every column of the lines (through Impuesto retención, Valor total and the actions) is visible
without scrolling the table (DOC-10); on a narrower screen the lines table scrolls sideways inside its box; the search
lists are not cut by it; the totals sit
to the right on a wide screen and below on a narrow one; the check mark and "Faltan…" read in both themes (icon and
words, not colour only). Read-only shows every value with nothing editable and no add, move, remove or tax buttons.
The console has no errors.

**DOC-10 · At 1366×768 every column of the lines is visible**
Smoke: `e2e/documentEditor.spec.ts`.
At 1366×768, a new factura de venta with a product on line 1, and a new factura de compra with line 1 by account.
**Expected:** Producto/Servicio, Descripción, Cantidad, Valor unitario, % Descuento, Impuesto cargo, Impuesto
retención, Valor total and Acciones all sit inside the table's box; the table does not scroll sideways.

## 8. Facturas de venta

<!-- Owned by item 8 "sales-invoice" (SAL-01 – 29). -->

Facturas de venta at **`/facturas-venta`**. Every case runs as the owner of a company that has its invoicing resolution
(prefix `FE`, Configuración › Resolución), a client **Distribuciones Andina S.A.S.** with a billing e-mail, and a
service **SRV-01 Consultoría mensual** of 1.000.000 with IVA 19 %, unless it says otherwise.

**SAL-01 · A company without invoices is told what the section is for**
Smoke: `e2e/salesInvoice.spec.ts`.
A brand-new company › **Facturas de venta** › **Nueva factura**.
**Expected:** the list says "Aún no has hecho facturas de venta…" with **Crear la primera factura**; the new invoice
warns "Aún no has registrado tu resolución de facturación…" and still lets a draft be saved.

**SAL-02 · An invoice paid half in cash and half at 30 days is saved, emitted and posted (AC-3)**
Smoke: `e2e/salesInvoice.spec.ts`.
**Nueva factura** › Cliente `Dis…` › Distribuciones Andina › line 1 `SRV-01` › **Agregar forma de pago** Efectivo
`595000` › **Agregar forma de pago** Crédito (*A 30 días*) › **Guardar** › **Emitir** › confirm.
**Expected:** Tipo reads "Factura electrónica de venta (FE)"; after Guardar "Borrador guardado." and the address is
the invoice's; after Emitir "Factura FE-1 emitida y contabilizada.", the title is "Factura de venta FE-1", everything is
read-only with "Saldo por cobrar: $ 595.000,00". In Contabilidad › Libro diario one entry: Dr 11050501 595.000, Dr
13050501 595.000 (the client), Cr 413595 1.000.000, Cr 240805 190.000.

**SAL-03 · Formas de pago that do not add up stop the emission**
Smoke: `e2e/salesInvoice.spec.ts`.
A draft of $ 1.190.000,00 with one Efectivo row of `100000` › **Emitir**.
**Expected:** no confirmation opens; "Revisa los campos marcados." and under Formas de pago "Total formas de pago
($ 100.000,00) debe ser igual al total neto ($ 1.190.000,00)."; nothing is numbered.

**SAL-04 · A new invoice warns when the resolution is running out (AC-8)**
Smoke: `e2e/salesInvoice.spec.ts` (numbers); by hand: days.
A resolution from 1 to 10 › **Nueva factura**; then one valid only 10 more days.
**Expected:** "Tu resolución de facturación se está acabando: quedan 10 números y … días de vigencia." on the new
invoice and on a draft, not on an emitted invoice.

**SAL-05 · An emitted invoice is voided with a reason and keeps its number (AC-7)**
Smoke: `e2e/salesInvoice.spec.ts`.
Open FE-1 › **Anular** › **Anular factura** with no reason › type `Precio equivocado` › **Anular factura**.
**Expected:** without a reason "Escribe el motivo de la anulación." and nothing changes; then "Factura FE-1 anulada.",
"Anulada el DD/MM/AAAA. Motivo: Precio equivocado", no Anular button, and the libro diario shows the reversing entry
dated today. The next invoice emitted is FE-2.

**SAL-06 · The list searches, filters by status and offers a way back**
Smoke: `e2e/salesInvoice.spec.ts` (search, status); by hand: Desde / Hasta.
An emitted FE-1 and a draft › filter **Estado** Borrador, then Anulada › **Ver todo** › search `FE-1`, then `andina`;
**Desde** / **Hasta** around a date.
**Expected:** each filter narrows the rows (newest first; a draft shows "Borrador" as its number); Anulada with none
says "Ninguna factura coincide con estos filtros." and **Ver todo** clears every filter; dates are typed as DD/MM/AAAA.
The rows are tinted by status with the legend above, and Total and Saldo are right-aligned.

**SAL-07 · An invoice is duplicated as a new draft**
Smoke: `e2e/salesInvoice.spec.ts`.
In the list, row FE-1 › **Duplicar**.
**Expected:** "Se creó un borrador nuevo a partir de la factura." on a new draft dated today with the same client,
lines and formas de pago (a crédito keeps its term), editable.

**SAL-08 · Emitir y enviar mails the PDF to the client**
Smoke: `e2e/salesInvoice.spec.ts`; by hand: open the attachment.
A draft › **Emitir y enviar** › confirm.
**Expected:** the dialog says the PDF goes to the client's billing e-mail; "Factura FE-1 emitida; el correo con el PDF
va en camino."; in Mailpit an e-mail "Factura de venta FE-1 de …" to the client with `factura-FE-1.pdf` attached.

**SAL-09 · The PDF carries what an invoice must say**
Smoke (part): `e2e/salesInvoice.spec.ts` checks it downloads; by hand: what it shows.
With a company logo › open FE-1 › **Descargar PDF**.
**Expected:** the logo and the company's razón social, NIT-DV, address; "Resolución DIAN No. … del …, vigente hasta el
…. Autoriza la numeración del FE-1 al FE-1000."; "No. FE-1"; the client's name, NIT, address and e-mail; the lines
with Cantidad, Valor unitario, % Desc., Impuesto and Valor total; Total bruto, IVA 19 % with its base, Total neto;
the formas de pago with their due dates; Observaciones. Money `$ 1.190.000,00`, dates DD/MM/AAAA. A draft's PDF reads
BORRADOR and has no number.

**SAL-10 · Another company's invoice does not exist (AC-10)**
Smoke: `e2e/salesInvoice.spec.ts`.
Copy the address of an invoice of company A; sign in as company B and open it.
**Expected:** "Esta factura no existe."; company B's list does not show it.

**SAL-11 · The PDF of a voided invoice says ANULADA (AC-7)**
Void FE-1 › **Descargar PDF**.
**Expected:** ANULADA across the page and "ANULADA el DD/MM/AAAA. Motivo: …" above the client; the number is still
FE-1.

**SAL-12 · An invoice is sent again from the list**
FE-1 emitted › in the list **Enviar**.
**Expected:** "El correo con el PDF de la factura FE-1 va en camino." and the e-mail arrives in Mailpit. A draft and a
voided invoice have no Enviar.

**SAL-13 · A client without e-mail cannot be emitted and sent**
A client created from the full form without Correo › a draft for it › **Emitir y enviar** › confirm.
**Expected:** the dialog stays open with "El cliente no tiene correo de facturación: agrégalo en su ficha o emite sin
enviar."; the invoice is still a draft; **Emitir** works.

**SAL-14 · Nothing is emitted past the resolution's hasta (AC-8)**
A resolution from 1 to 2 › emit two invoices › a third draft › **Emitir**.
**Expected:** the third stays a draft and the dialog says "La resolución de facturación ya no tiene números
disponibles."; Configuración › Resolución shows it exhausted.

**SAL-15 · An invoice dated outside the resolution, in the future or in a closed period is not emitted (AC-8, AC-9)**
A draft dated before the resolution's fecha inicio › **Emitir**; then dated tomorrow; then, with the fecha de bloqueo
(Contabilidad) moved to yesterday, dated yesterday.
**Expected:** "La fecha de la factura está fuera de la vigencia de la resolución."; then Fecha de elaboración is
marked "La fecha de la factura no puede ser futura."; then "La fecha está en un periodo contable cerrado (fecha de
bloqueo)." Each time nothing is numbered and the libro diario is unchanged. A draft dated a week ago (open period) is
emitted and posted on that date.

**SAL-16 · A void today in a closed period is refused (AC-9)**
FE-1 emitted › move the fecha de bloqueo to today › **Anular** with a reason.
**Expected:** the dialog stays open with "La fecha está en un periodo contable cerrado (fecha de bloqueo)." and FE-1 is
unchanged.

**SAL-17 · An inactive client is not invoiced**
A draft for a client › deactivate the client in Terceros › **Emitir**; then **Nueva factura** and search the client.
**Expected:** "El cliente está inactivo: actívalo para facturarle."; a new invoice cannot choose it.

**SAL-18 · A draft is edited and saved again**
A saved draft › change Cantidad to `3`, add a line, remove a forma de pago › **Guardar**; reload the page.
**Expected:** "Borrador guardado."; after the reload the draft has exactly what was saved and its totals match the
preview; Número still reads as pending.

**SAL-19 · The server's checks show next to their fields**
A draft whose crédito due date is before Fecha de elaboración (type the date by hand), saved › **Guardar**.
**Expected:** under the due date a message that it cannot be before Fecha de elaboración, and nothing is saved.

**SAL-20 · Discounts and retenciones are posted as A.1**
Line SRV-01, Cantidad `2`, % Descuento `10`, ReteFuente servicios 4 %, all on Crédito › emit.
**Expected:** Total neto `$ 2.070.000,00`; in the libro diario Dr 13050501 2.070.000, Dr 417501 200.000, Dr 135515
72.000, Cr 413595 2.000.000, Cr 240805 342.000 (débitos = créditos = 2.342.000).

**SAL-21 · A product's own revenue account is credited**
Productos y servicios › SRV-01 › Cuenta de ingreso `415595` › invoice it and emit.
**Expected:** the libro diario credits 415595 instead of 413595.

**SAL-22 · The accountant reads invoices and cannot change them**
Signed in as the accountant (section 1).
**Expected:** the list and every invoice open, the PDF downloads; no Nueva factura, Guardar, Emitir, Duplicar, Enviar
or Anular anywhere.

**SAL-23 · A billing user invoices**
Signed in as a billing user: create, save, emit and void an invoice.
**Expected:** everything works as for the owner.

**SAL-24 · An invoice with a receipt applied is not voided**
Needs the recibo de caja (item "cash-receipt"). FE-1 on crédito › a recibo de caja for part of it › FE-1 › **Anular**.
**Expected:** Pagada parcialmente, no Anular in the list; the API answers "Esta factura tiene recibos de caja
aplicados: anúlalos primero."; after voiding the receipt FE-1 is Emitida and can be voided.

**SAL-25 · The screens on a tablet, in both themes**
At 1366×768, 1024×768 and 768×1024, Tema Claro and Oscuro: the list with filters, a draft, an emitted and a voided
invoice, the emit and void dialogs.
**Expected:** the header actions wrap without overlapping; the filters fit; badges and warnings read in both themes;
the console has no errors.

## 9. Facturas de compra / gasto

<!-- Owned by item 9 "purchase-invoice" (PUR-01 – 29). -->

Open **Facturas de compra** (`/facturas-compra`). The cases need a supplier (Terceros, role Proveedor) and the seeded
taxes and payment methods; PUR-13 onwards look at the books, so run them after the ledger is merged.

**PUR-01 · A new company sees what the section is for and how to start**
Smoke: `e2e/purchaseInvoice.spec.ts`.
A company with no purchase invoices › open Facturas de compra.
**Expected:** "Aún no has registrado facturas de compra…" with **Registrar la primera factura de compra**, and
**Nueva factura de compra** in the header; no table, no error.

**PUR-02 · A service bought on credit is saved as a draft from the form**
Smoke: `e2e/purchaseInvoice.spec.ts`.
**Nueva factura de compra** › Proveedor (type 3 letters, choose it) › Número de factura del proveedor `FAC-881` › line 1
Tipo **Cuenta de gasto**, `513595`, Mantenimiento de equipos, 1 × 1000000, IVA 19 %, ReteFuente servicios 4 % ›
**Agregar forma de pago** › Crédito › **Guardar borrador**.
**Expected:** the forma de pago offers `1150000.00` (the total neto) and a due date 30 days on; "Borrador guardado.";
the title is "Factura de compra (borrador)" and the address is the invoice's own. By hand: totals read Total bruto
$ 1.000.000,00, Impuestos $ 190.000,00, Retenciones $ 40.000,00, Total neto $ 1.150.000,00.

**PUR-03 · Emitting numbers the invoice, posts it and leaves it read-only**
Smoke: `e2e/purchaseInvoice.spec.ts`.
Open the draft of PUR-02 › **Emitir**.
**Expected:** "Factura FC-1 emitida y contabilizada."; the title is "Factura de compra FC-1"; every field is disabled,
with **PDF**, **Duplicar** and **Anular** in the header. The libro diario shows one entry FC-1: Dr 513595 1.000.000,
Dr 240810 190.000, Cr 236525 40.000, Cr 22050501 1.150.000 (acceptance criterion 5).

**PUR-04 · The supplier's number is not recorded twice for the same supplier**
Smoke: `e2e/purchaseInvoice.spec.ts`.
A second draft for the same supplier › Número de factura del proveedor `fac-881` (other letter case) › **Guardar
borrador**.
**Expected:** under the field "Ya registraste una factura de este proveedor con este número."; nothing saved. By hand:
the same number for another supplier saves.

**PUR-05 · Emitting needs formas de pago that add up to the total neto**
Smoke: `e2e/purchaseInvoice.spec.ts`.
In a draft change the forma de pago to `1000000` › **Emitir**.
**Expected:** "Revisa los campos marcados." and "Faltan $ 150.000,00 para el total neto"; it stays a draft.

**PUR-06 · The list shows each invoice with its numbers, money and actions**
Smoke: `e2e/purchaseInvoice.spec.ts`.
With FC-1 emitted and a second draft.
**Expected:** a row FC-1 with the supplier, FAC-881, the date and due date as DD/MM/AAAA, $ 1.150.000,00 total and
saldo, and **Abrir**, **PDF**, **Duplicar**, **Anular**; the draft reads "Borrador" (tinted, in the legend) and has no
Anular. By hand: the row colours and the legend agree, in light and dark.

**PUR-07 · Search and the status filter narrow the list, and "Ver todo" brings it back**
Smoke: `e2e/purchaseInvoice.spec.ts`.
Search `882`; clear it; Estado `Emitida`; Estado `Pagada`; **Ver todo**.
**Expected:** each narrows the table; with nothing "Ninguna factura de compra coincide con lo que buscas." and **Ver
todo** clears every filter. By hand: searching the internal number (`FC-1`) or the supplier's name finds it; Desde /
Hasta (DD/MM/AAAA) filter by the invoice date; the address keeps the filters and reloading keeps them.

**PUR-08 · Voiding from the list asks why and keeps the number**
Smoke: `e2e/purchaseInvoice.spec.ts`.
**Anular** on FC-1 › **Anular factura** with no reason › write `Registrada dos veces` › **Anular factura**.
**Expected:** first "Escribe por qué se anula la factura."; then "Factura FC-1 anulada.", the row reads Anulada and
has no Anular. By hand: the libro diario has a second entry FC-1 dated today with débitos and créditos swapped;
cartera owes nothing on it; the next invoice emitted is FC-2, never FC-1 again (acceptance criterion 7).

**PUR-09 · Duplicating makes a new draft without the supplier's number**
Smoke: `e2e/purchaseInvoice.spec.ts`.
**Duplicar** on FC-1.
**Expected:** "Se creó un borrador igual. Escribe el número de factura del proveedor."; a draft dated today with the
same supplier, lines, taxes and formas de pago (a 30-day crédito is still 30 days), the supplier's number empty.

**PUR-10 · The supplier's PDF is attached to a draft and can be downloaded**
Smoke: `e2e/purchaseInvoice.spec.ts`.
In a draft, **Adjuntar archivo** › a PDF; then a PNG renamed `.pdf`.
**Expected:** "Archivo adjunto." and the file listed with **Descargar …**, which downloads it; the PNG is refused with
"Adjunta la factura del proveedor en PDF o XML.". By hand: an XML (the supplier's electronic invoice) is accepted; a
file over 10 MB says "El archivo pesa más de 10 MB."; the trash icon removes a file from a draft.

**PUR-11 · The PDF of an emitted invoice downloads**
Smoke (part): `e2e/purchaseInvoice.spec.ts` checks it is a PDF.
**PDF** on FC-1. By hand: open it.
**Expected:** the company's header (logo when it has one, NIT-DV), "Factura de compra" No. FC-1, the supplier and its
number, dates, the lines with their taxes, the formas de pago and the totals; a voided one shows ANULADA across it and
its reason.

**PUR-12 · A draft is deleted after confirming**
Smoke: `e2e/purchaseInvoice.spec.ts`.
Open a draft › **Eliminar borrador** › **Eliminar**.
**Expected:** "Borrador eliminado." back on the list, and it is gone. An emitted invoice has no Eliminar (it is voided).

**PUR-13 · Lines by product post to the product's account, 6205 or gasto por defecto**
A draft with three lines: a producto with its own Cuenta de gasto, a producto without one, a servicio without one;
contado (Efectivo) for the whole › **Emitir**.
**Expected:** the entry debits the product's account, 620501 Compras de mercancías and 519595 (gasto por defecto), and
credits 11050501 Caja; no payable is created (nothing on credit).

**PUR-14 · A discount is netted on the line and impoconsumo is part of the cost**
A line of 2 × 500.000 with 10 % off and IVA 19 %; another of 100.000 with Impoconsumo 8 %.
**Expected:** the gasto is debited 900.000 (no discount account on purchases) and the second line 108.000 (impoconsumo
included); IVA descontable 171.000.

**PUR-15 · No purchase is emitted on or before the fecha de bloqueo**
Move the fecha de bloqueo (Configuración › Reglas contables) to yesterday › emit a draft dated yesterday.
**Expected:** "La contabilidad está cerrada hasta el DD/MM/AAAA: usa una fecha posterior."; it stays a draft and the
FC number is not spent (the next emission takes it). A date in the future says "La fecha de la factura no puede ser
futura." (acceptance criterion 9).

**PUR-16 · An inactive supplier cannot be emitted to**
Deactivate the supplier of a draft (Terceros) › **Emitir**.
**Expected:** "El proveedor está inactivo: reactívalo para emitir."

**PUR-17 · Only accounts usable on purchases are offered on a line**
Tipo **Cuenta de gasto** › type `1105`, then `5135`.
**Expected:** caja is not offered (and typing its code is refused under the cell when saving); 5135xx sub-accounts are,
and so is any account the accountant marked "usable en compras" (§9 Q14).

**PUR-18 · A supplier on credit owes the net amount, paid down by its payments**
After PUR-03 (and once "supplier-payment" is merged) pay part of FC-1 with a recibo de pago.
**Expected:** FC-1 reads Pagada parcialmente with its saldo; it can no longer be voided ("La factura tiene pagos
aplicados: anula primero los recibos de pago."); paying the rest makes it Pagada.

**PUR-19 · The accountant reads purchases but does not change them**
Sign in as the accountant › Facturas de compra.
**Expected:** the list and each invoice open (PDF and the supplier's files download); no Nueva, Guardar, Emitir,
Duplicar, Anular or Eliminar; the form is read-only.

**PUR-20 · Another company's invoice is not found**
Copy the address of an invoice › sign in as another company's user › open it.
**Expected:** "No pudimos cargar la factura de compra." with Reintentar; nothing of the other company shows
(acceptance criterion 10).

**PUR-21 · The screens at laptop and tablet widths, light and dark**
The list, a draft, an emitted and a voided invoice, the void and delete dialogs.
**Expected:** no horizontal page scroll; the header's buttons wrap; the supplier's number and due date sit with the
header fields; money and dates in Colombian format; the console has no errors.

## 10. Cotizaciones

<!-- Owned by item 10 "quotation" (COT-01 – 19). -->

Cotizaciones at **`/cotizaciones`**. Every case runs as the owner of a company with a client **Distribuciones Andina
S.A.S.** (with an e-mail) and a service **SRV-01 Consultoría mensual** of 1.000.000 with IVA 19 %, unless it says
otherwise. A quotation needs no invoicing resolution. An emitted quotation past its *Válida hasta* date reads as
**Vencida**: it is worked out from the date each time it is read, nothing runs at night.

**COT-01 · A company without quotations is told what the section is for**
Smoke: `e2e/quotation.spec.ts`.
A brand-new company › **Cotizaciones** › **Nueva cotización**.
**Expected:** the list says "Aún no has hecho cotizaciones…" with **Crear la primera cotización**; the new quotation
shows Tipo "Cotización", the extra fields **Responsable de la cotización**, **Válida hasta** (30 days from today, as
DD/MM/AAAA), **Encabezado** and **Condiciones comerciales**, and no *Formas de pago* section.

**COT-02 · A quotation is saved with its own fields, emitted as C-1 and posts nothing (AC-2)**
Smoke: `e2e/quotation.spec.ts`.
**Nueva cotización** › Cliente `Dis…` › line 1 `SRV` › Responsable (an employee) › type an Encabezado and Condiciones ›
**Guardar** › **Emitir** › confirm.
**Expected:** "Borrador guardado." and the address is the quotation's; after Emitir "Cotización C-1 emitida.", the title
is "Cotización C-1", everything is read-only ("Esta cotización ya fue emitida…") with the texts as typed. Contabilidad ›
Libro diario has no entry.

**COT-03 · A quotation without its client or lines is not emitted**
Smoke: `e2e/quotation.spec.ts`.
**Nueva cotización** › **Emitir** with nothing filled.
**Expected:** no confirmation opens; "Revisa los campos marcados." with the client and the line marked; nothing is
numbered.

**COT-04 · Emitir y enviar mails the PDF to the client**
Smoke: `e2e/quotation.spec.ts`; by hand: the e-mail's look.
A saved draft › **Emitir y enviar** › confirm; open Mailpit.
**Expected:** the dialog says the PDF goes to the client's e-mail; "Cotización C-1 emitida; el correo con el PDF va en
camino."; Mailpit shows "Cotización C-1 de <empresa>" to the client with the PDF attached and the *Válida hasta* date in
the text. A client without e-mail: "El cliente no tiene correo…" and nothing is emitted.

**COT-05 · The client accepts one quotation and rejects another**
Smoke: `e2e/quotation.spec.ts`.
Two emitted quotations › open C-1 › **Aceptar** › confirm; open C-2 › **Rechazar** › confirm.
**Expected:** "Cotización C-1 aceptada." (no more Rechazar; Convertir a factura still offered); "Cotización C-2 rechazada."
(no Convertir a factura). The list shows both statuses.

**COT-06 · Converting makes a draft invoice with the same lines and the quotation shows Aceptada (AC-2)**
Smoke: `e2e/quotation.spec.ts`.
An emitted quotation with two lines, one with discount and ReteFuente › **Convertir a factura** › confirm.
**Expected:** the draft invoice opens ("Factura de venta · borrador", "Se creó este borrador de factura a partir de la
cotización C-1.") with the same client, lines, taxes and totals (compare both pages) and no formas de pago yet; back on
the quotation: "Aceptada", **Ver la factura** opens the draft, and there is no Convertir a factura any more. The libro
diario still has no entry.

**COT-07 · What the invoice refuses is listed on the quotation, and nothing is converted**
Smoke: `e2e/quotation.spec.ts` (product); by hand: tax and client.
Emit a quotation, then deactivate its product (Productos), then an IVA it uses (Configuración › Impuestos), then the
client (Terceros) — one at a time, converting after each.
**Expected:** a red box "No se pudo convertir la cotización" lists "Línea 1: Este producto está inactivo." (or the tax,
or "Este cliente está inactivo."); the quotation stays Emitida, no invoice was made (Facturas de venta is empty),
Convertir a factura is still offered. **Duplicar** gives a draft the person can correct.

**COT-08 · An emitted quotation past its vencimiento reads as Vencida**
Smoke: `e2e/quotation.spec.ts`.
An emitted quotation dated 40 days ago (30 days of validity) and a current one › list › **Estado** Vencida; open the old one.
**Expected:** only the old one is listed, as Vencida; its page says "La oferta venció el DD/MM/AAAA: ya no se puede
aceptar ni convertir…" and offers only Anular, PDF and Duplicar. On its last valid day it still reads Emitida.

**COT-09 · An emitted quotation is voided with a reason and keeps its number**
Smoke: `e2e/quotation.spec.ts`.
**Anular** › **Anular cotización** with no reason › type `Cambió el alcance` › **Anular cotización**.
**Expected:** without a reason "Escribe el motivo de la anulación."; then "Cotización C-1 anulada.", "Anulada el
DD/MM/AAAA. Motivo: Cambió el alcance", no more actions but PDF and Duplicar. The libro diario has nothing to reverse.

**COT-10 · The list searches, filters by status and duplicates**
Smoke: `e2e/quotation.spec.ts` (search, status, duplicate); by hand: Desde / Hasta.
An emitted C-1 and a draft › **Estado** Borrador, then Anulada › **Ver todo** › search `C-1`, then `andina` ›
**Desde** / **Hasta** around a date › **Duplicar** on C-1.
**Expected:** each filter narrows the rows (newest first; a draft shows "Borrador" as its number); Anulada with none says
"Ninguna cotización coincide con estos filtros."; **Ver todo** clears every filter; dates are typed as DD/MM/AAAA; the
status filter offers Borrador, Emitida, Aceptada, Rechazada, Vencida and Anulada. Duplicar opens a new draft dated today
with the same client and lines, *Válida hasta* 30 days from today.

**COT-11 · What is typed in the texts is shown as typed, never as markup**
Smoke: `e2e/quotation.spec.ts` (screen); by hand: PDF.
Save a quotation with Encabezado `<b>Hola</b> <script>alert(1)</script>` and Condiciones `<img src=x onerror=alert(1)>`,
emit it, open it, download the PDF.
**Expected:** the screen shows the characters exactly as typed, no bold text, no alert; the PDF shows the same
characters (escaped), with a blank line in the text starting a new paragraph and a single line break kept inside one.

**COT-12 · The PDF downloads and another company's quotation does not exist (AC-10)**
Smoke: `e2e/quotation.spec.ts`.
**PDF** on an emitted quotation; then, signed in to another company, open the first one's address.
**Expected:** a PDF named `cotizacion-C-1.pdf`; the other company sees "Esta cotización no existe." and its list is empty.

**COT-13 · The PDF reads like a quotation**
By hand.
Download the PDF of an emitted quotation with a logo, a responsable, an Encabezado, two lines, a discount, IVA, a
ReteFuente and Condiciones comerciales; then one of a draft and one voided.
**Expected:** the company's header and logo; "Cotización" and "No. C-1"; the client's fiscal data and contact; Fecha de
elaboración, Válida hasta and Responsable; the Encabezado above the lines; lines, totals and taxes in Colombian format;
Condiciones comerciales and Observaciones below. No *Formas de pago*. A draft is marked BORRADOR (no number), a voided
one ANULADA with the reason.

**COT-14 · The responsable is an employee and the vencimiento follows the date**
By hand.
**Nueva cotización** › open **Responsable**; change **Fecha de elaboración** to another day; then type a **Válida hasta**
and change the date again; type a Válida hasta before the date and **Guardar**.
**Expected:** Responsable lists only active terceros with the role empleado (and "Sin responsable"); Válida hasta moves
with the date (always 30 days) until it is typed by hand, then stays; one before the date is refused ("La oferta no
puede vencer antes de la fecha de la cotización.") next to the field.

**COT-15 · A draft is edited, an emitted quotation is not**
By hand.
Save a draft › change a line and the texts › **Guardar** › reload; **Emitir** › try to change anything.
**Expected:** the changes persist; after emitting every field is disabled and there is no Guardar or Emitir; a second
**Emitir** in another tab says "Esta cotización ya fue emitida: no se puede modificar."

**COT-16 · A quotation converts only once**
By hand.
Convert a quotation; in another tab opened before, press **Convertir a factura**; also **Aceptar** a quotation by hand
first and then convert it.
**Expected:** the second tab says "Esta cotización ya se convirtió en factura: solo se convierte una vez." and no second
invoice exists; a quotation accepted by hand converts normally, once.

**COT-17 · Roles**
By hand (after the invitations of section 1).
As a billing user, then as the accountant: open Cotizaciones and a quotation.
**Expected:** the billing user does everything above; the accountant sees the list and the PDF but no Nueva cotización,
Guardar, Emitir, Enviar, Aceptar, Rechazar, Convertir ni Anular (and the API answers 403 to them).

**COT-18 · The accepted draft invoice is emitted like any other**
By hand (needs the invoicing resolution).
Convert a quotation › on the draft invoice add a forma de pago for the whole total › **Emitir**.
**Expected:** the invoice is FE-1 and posts as in SAL-02; the quotation stays Aceptada and its **Ver la factura** link
opens that invoice.

**COT-19 · Layout**
By hand, at 1366 px and at a phone width.
Open the list, a new quotation and an emitted one.
**Expected:** no horizontal page scroll; the header's buttons wrap; Responsable and Válida hasta sit with the header
fields and the two texts with the footer; the red conversion box reads well; money and dates in Colombian format; the
console has no errors.

## 11. Recibos de caja

<!-- Owned by item 11 "cash-receipt" (RC-01 – 19). -->

Recibos de caja at **`/recibos-caja`**. Every case runs as the owner of a company with its invoicing resolution (prefix
`FE`), a client **Distribuciones Andina S.A.S.** with a billing e-mail, and emitted facturas de venta for it with a part
on **Crédito** (section 8), unless it says otherwise. A receipt has no draft: **Guardar** emits it.

**RC-01 · A company without receipts is told what the section is for**
Smoke: `e2e/cashReceipt.spec.ts`.
A brand-new company › **Recibos de caja** › **Registrar el primer recibo**.
**Expected:** the list says "Aún no has registrado recibos de caja…"; the new receipt says "Elige el cliente para ver
sus facturas pendientes.", *Dónde ingresa el dinero* lists Efectivo, Tarjeta débito, Tarjeta crédito and Transferencia
but not Crédito, Fecha is today as DD/MM/AAAA, and **Guardar** is disabled.

**RC-02 · A receipt for the 30-day receivable pays the invoice (AC-4)**
Smoke: `e2e/cashReceipt.spec.ts`; by hand: the libro diario.
The invoice of SAL-02 (595.000 cash, 595.000 at 30 days) › **Nuevo recibo** › Cliente `Dis…` › Distribuciones Andina ›
Transferencia › Valor recibido `595.000` › **Pagar todo** on FE-1 › **Guardar**.
**Expected:** the table shows FE-1 with fecha, vencimiento (30 days on), valor and saldo $ 595.000,00; "✓ Cuadra…" once
the row is paid; "Recibo RC-1 guardado y contabilizado." on the receipt, read-only. FE-1 is **Pagada**. Libro diario:
one entry RC-1, Dr 11100501 595.000, Cr 13050501 595.000 (the client); the client's 1305 balance is zero.

**RC-03 · The running difference holds Guardar until it is zero**
Smoke: `e2e/cashReceipt.spec.ts`.
Two invoices on crédito › Valor recibido `700000` › `595000` on the first › `200000` on the second › `595000,01` on the
first › `500000` on the first.
**Expected:** "Falta aplicar $ 105.000,00.", then "Aplicaste $ 95.000,00 de más.", then "Supera el saldo de la
factura." under the row, each with Guardar disabled; then "✓ Cuadra…" and Guardar enabled. Amounts may be typed as
`595.000`, `595000,5` or `595000.50`.

**RC-04 · A receipt is voided with a reason and the invoice is owed again**
Smoke: `e2e/cashReceipt.spec.ts`; by hand: the libro diario.
A receipt paying FE-1 in full › open it › **Anular** › **Anular recibo** with no reason › `Cheque devuelto` › **Anular
recibo**.
**Expected:** without a reason "Escribe el motivo de la anulación."; then "Recibo RC-1 anulado.", "Anulado el … Motivo:
Cheque devuelto", no Anular; FE-1 is **Emitida** with its saldo again; the libro diario shows the reversing entry
dated today; the next receipt is RC-2.

**RC-05 · The list searches, filters and offers a way back**
Smoke: `e2e/cashReceipt.spec.ts` (status, search); by hand: Desde / Hasta.
Two receipts › **Estado** Anulado › **Ver todo** › search `RC-1`, then `andina`; **Desde** / **Hasta** around a date.
**Expected:** newest first, each row with its number and badge, cliente, fecha, dónde ingresó, the invoices it paid and
the valor (right-aligned), tinted by status with the legend; Anulado with none says "Ningún recibo coincide con estos
filtros." and **Ver todo** clears every filter.

**RC-06 · Guardar y enviar mails the PDF to the client**
Smoke: `e2e/cashReceipt.spec.ts`; by hand: open the attachment.
A receipt filled in › **Guardar y enviar**.
**Expected:** "Recibo RC-1 guardado; el correo con el PDF va en camino."; in Mailpit "Recibo de caja RC-1 de …" to the
client's billing e-mail with `recibo-de-caja-RC-1.pdf` attached. **Enviar** in the list or on the receipt sends it again.

**RC-07 · A receipt that does not exist, or another company's, says so**
Smoke: `e2e/cashReceipt.spec.ts`.
Open `/recibos-caja/<an id of another company>`.
**Expected:** "Este recibo no existe." with **Volver a recibos**.

**RC-08 · The PDF reads as a recibo de caja, and ANULADA once voided**
Open a receipt › **Descargar PDF**; then void it and download again.
**Expected:** the company's header and logo, "Recibo de caja", "No. RC-1", the client with its NIT, the date, where the
money came in, each invoice with the amount applied, "Total recibido", the observaciones and a signature line; after
the void *ANULADA* across the page and "ANULADO el … Motivo: …".

**RC-09 · A client with nothing owed, and a refusal from the server**
A client whose invoices are all cash › **Nuevo recibo** › choose it. Then, in two tabs, the same receivable paid in full
in each › **Guardar** in the first, then in the second.
**Expected:** "Este cliente no tiene facturas pendientes por cobrar."; the second tab is refused with "Un valor aplicado
supera el saldo de su factura…" and the table reloads with the saldo now left (zero rows if paid).

**RC-10 · Nothing is received on or before the lock date (AC-9)**
Contabilidad › fecha de bloqueo yesterday › a receipt dated yesterday › **Guardar**.
**Expected:** "La fecha está en un periodo contable cerrado (fecha de bloqueo)." and nothing is numbered or applied; a
receipt dated today saves. With the lock date today, **Anular** is refused the same way.

**RC-11 · An invoice with a receipt applied is voided only after the receipt**
FE-1 on crédito with a partial receipt (RC-04 without the void) › FE-1 in Facturas de venta.
**Expected:** FE-1 is Pagada parcialmente without Anular; after voiding the receipt, FE-1 is Emitida and can be voided
(SAL-24).

**RC-12 · The accountant reads receipts and cannot change them**
Signed in as the accountant (section 1).
**Expected:** the list and every receipt open and the PDF downloads; no Nuevo recibo, Enviar or Anular anywhere;
`/recibos-caja/nuevo` says "Tu rol no puede hacer esto con los recibos de caja."

**RC-13 · A billing user receives**
Signed in as a billing user: create, send and void a receipt.
**Expected:** everything works as for the owner.

**RC-14 · The screens on a tablet, in both themes**
At 1366×768, 1024×768 and 768×1024, Tema Claro and Oscuro: the list with filters, a new receipt with three invoices,
a receipt and a voided one, the void dialog.
**Expected:** the client search list opens under its box; the allocation table's amount inputs and Pagar todo stay
visible (the table scrolls inside its frame on a narrow screen); the difference line reads in both themes; the console
has no errors.

## 12. Recibos de pago / egreso

<!-- Owned by item 12 "supplier-payment" (PAY-01 – 19). -->

Recibos de pago at **`/recibos-pago`**. Every case runs as the owner of a company with a supplier **Servicios Andinos
S.A.S.** (role Proveedor, with an e-mail) and emitted facturas de compra for it with a part on **Crédito** (section 9:
a service of 1.000.000 with IVA 19 % and ReteFuente 4 % is a payable of 1.150.000), unless it says otherwise. A payment
has no draft: **Guardar** emits it.

**PAY-01 · A company without payments is told what the section is for**
Smoke: `e2e/supplierPayment.spec.ts`.
A brand-new company › **Recibos de pago** › **Registrar el primer recibo de pago**.
**Expected:** the list says "Aún no has registrado recibos de pago…"; the new payment says "Elige el proveedor para ver
sus facturas pendientes de pago.", *De dónde sale el dinero* lists Efectivo, Tarjeta débito, Tarjeta crédito and
Transferencia but not Crédito, Fecha is today as DD/MM/AAAA, and **Guardar** is disabled.

**PAY-02 · A payment clears the payable and pays the purchase invoice (AC-6)**
Smoke: `e2e/supplierPayment.spec.ts`; by hand: the libro diario and the balance de prueba.
A factura de compra of 1.150.000 on crédito › **Nuevo recibo de pago** › Proveedor `Ser…` › Servicios Andinos ›
Transferencia › Valor pagado `1.150.000` › **Pagar todo** on the invoice › **Guardar**.
**Expected:** the table shows the invoice with fecha, vencimiento (30 days on), valor and saldo $ 1.150.000,00; "✓
Cuadra…" once the row is paid; "Recibo de pago RP-1 guardado y contabilizado." on the payment, read-only. The factura de
compra is **Pagada**. Libro diario: one entry RP-1, Dr 22050501 1.150.000 (the supplier), Cr 11100501 1.150.000; the
supplier's 2205 balance is zero and the balance de prueba still balances.

**PAY-03 · The running difference holds Guardar until it is zero**
Smoke: `e2e/supplierPayment.spec.ts`.
Two invoices on crédito › Valor pagado `700000` › `595000` on the first › `200000` on the second › `595000,01` on the
first › `500000` on the first.
**Expected:** "Falta aplicar $ 105.000,00.", then "Aplicaste $ 95.000,00 de más.", then "Supera el saldo de la
factura." under the row, each with Guardar disabled; then "✓ Cuadra…" and Guardar enabled.

**PAY-04 · A payment is voided with a reason and the invoice is owed again**
Smoke: `e2e/supplierPayment.spec.ts`; by hand: the libro diario.
A payment paying the invoice in full › open it › **Anular** › **Anular recibo de pago** with no reason › `Transferencia
rechazada` › **Anular recibo de pago**.
**Expected:** without a reason "Escribe el motivo de la anulación."; then "Recibo de pago RP-1 anulado.", "Anulado el
… Motivo: Transferencia rechazada", no Anular; the factura de compra is **Emitida** with its saldo again; the libro
diario shows the reversing entry dated today; the next payment is RP-2.

**PAY-05 · The list searches, filters and offers a way back**
Smoke: `e2e/supplierPayment.spec.ts` (status, search); by hand: Desde / Hasta.
Two payments › **Estado** Anulado › **Ver todo** › search `RP-1`, then `andinos`; **Desde** / **Hasta** around a date.
**Expected:** newest first, each row with its number and badge, proveedor, fecha, de dónde salió, the invoices it paid
and the valor (right-aligned), tinted by status with the legend; Anulado with none says "Ningún recibo coincide con
estos filtros." and **Ver todo** clears every filter.

**PAY-06 · Guardar y enviar mails the PDF to the supplier**
Smoke: `e2e/supplierPayment.spec.ts`; by hand: open the attachment.
A payment filled in › **Guardar y enviar**.
**Expected:** "Recibo de pago RP-1 guardado; el correo con el PDF va en camino."; in Mailpit "Recibo de pago RP-1 de …"
to the supplier's e-mail with `recibo-de-pago-RP-1.pdf` attached. **Enviar** in the list or on the payment sends it
again. A supplier without e-mail: "El proveedor no tiene correo…" and nothing is saved.

**PAY-07 · A payment that does not exist, or another company's, says so**
Smoke: `e2e/supplierPayment.spec.ts`.
Open `/recibos-pago/<an id of another company>`.
**Expected:** "Este recibo de pago no existe." with **Volver a recibos de pago**.

**PAY-08 · The PDF reads as a recibo de pago, and ANULADA once voided**
Open a payment › **Descargar PDF**; then void it and download again.
**Expected:** the company's header and logo, "Recibo de pago", "No. RP-1", the supplier with its NIT, the date, where
the money went out from, each invoice with the amount applied, "Total pagado", the observaciones and a signature line;
after the void *ANULADA* across the page and "ANULADO el … Motivo: …".

**PAY-09 · A supplier with nothing owed, and a refusal from the server**
A supplier whose invoices are all cash › **Nuevo recibo de pago** › choose it. Then, in two tabs, the same payable paid
in full in each › **Guardar** in the first, then in the second.
**Expected:** "Este proveedor no tiene facturas pendientes de pago."; the second tab is refused with "Un valor aplicado
supera el saldo de su factura…" and the table reloads with the saldo now left (zero rows if paid).

**PAY-10 · Nothing is paid on or before the lock date (AC-9)**
Contabilidad › fecha de bloqueo yesterday › a payment dated yesterday › **Guardar**.
**Expected:** "La fecha está en un periodo contable cerrado (fecha de bloqueo)." and nothing is numbered or applied; a
payment dated today saves. With the lock date today, **Anular** is refused the same way.

**PAY-11 · An invoice with a payment applied is voided only after the payment**
A factura de compra with a partial payment (PAY-04 without the void) › the invoice in Facturas de compra.
**Expected:** the invoice is Pagada parcialmente and cannot be voided ("tiene pagos aplicados"); after voiding the
payment it is Emitida and can be voided.

**PAY-12 · The accountant pays and voids like billing**
Signed in as the accountant (section 1): create, send and void a payment.
**Expected:** everything works as for the owner (decided 2026-10-04: the accountant does every document action).

**PAY-13 · A billing user pays**
Signed in as a billing user: create, send and void a payment.
**Expected:** everything works as for the owner.

**PAY-14 · The supplier search only offers suppliers**
A client-only tercero named like the supplier › **Nuevo recibo de pago** › Proveedor `Ser…`.
**Expected:** only terceros with the role Proveedor are listed; a search with fewer than 3 characters says "Escribe al
menos 3 caracteres."; a name that matches nobody says "Ningún proveedor coincide."

**PAY-15 · Cash receipts are unchanged by the shared allocation table**
Open **Nuevo recibo de caja** with a client's invoices (section 11).
**Expected:** the table still reads "Valor recibido", "Cuadra: el valor aplicado es igual al valor recibido." (the
recibo de pago says "valor pagado").

**PAY-16 · The screens on a tablet, in both themes**
At 1366×768, 1024×768 and 768×1024, Tema Claro and Oscuro: the list with filters, a new payment with three invoices, a
payment and a voided one, the void dialog.
**Expected:** the supplier search list opens under its box; the allocation table's amount inputs and Pagar todo stay
visible (the table scrolls inside its frame on a narrow screen); the difference line reads in both themes; the console
has no errors.

## 13. Cartera, reports and dashboard

<!-- Owned by item 13 "reports" (REP-01 – 19). -->

The dashboard at **`/`**, and **Reportes** at **`/reportes`** (tabs *Cartera de clientes*, *Cartera de proveedores*,
*Exportar*). Every case runs as the owner of a company with an invoicing resolution, a client **Ana Ltda.** and
facturas de venta of 1.190.000 on **Crédito** (section 8), and a supplier **Servicios Andinos S.A.S.** with facturas de
compra on crédito (section 9: a payable of 1.150.000), unless it says otherwise. Ageing is by **vencimiento** against
the *Al corte* date (today by default): al día (not yet due, or due that day), 1–30, 31–60, 61–90 and más de 90 días.
CSV files use UTF-8 with a BOM, `;` between columns and a decimal **point** with no thousands separator.

**REP-01 · A new company sees zeros, the quick links and the missing resolution**
Smoke: `e2e/reports.spec.ts`.
A brand-new company with no resolution › **Tablero**.
**Expected:** every figure is $ 0,00; "Aún no has registrado tu resolución de facturación…" with **Ir a la resolución**
(to Configuración › Resolución); **Crear factura de venta**, **Crear recibo de caja** and **Crear factura de compra**
open their forms.

**REP-02 · The dashboard shows cartera, the month and the cash**
Smoke: `e2e/reports.spec.ts`; by hand: the same figures in the libro diario.
An invoice on crédito due in 20 days, another issued 100 days ago and long overdue, a purchase on crédito › **Tablero**.
**Expected:** cartera de clientes shows the total owed and, under it, what is vencido; cartera de proveedores likewise;
*Ventas del mes* and *Compras del mes* count only invoices of this month, before IVA (the old one and voided ones
count nothing); *Caja y bancos* is the 1105 + 1110 balance of the books; **Ver cartera** opens the report. No warning
about the resolution while it is healthy.

**REP-03 · Cartera de clientes by ageing bucket, with totals**
Smoke: `e2e/reports.spec.ts`.
Five invoices of 1.190.000 for Ana Ltda., due in 10 days and 5, 45, 75 and 120 days ago › **Reportes**.
**Expected:** it opens on *Cartera de clientes*; the Ana Ltda. row (name and NIT) has $ 1.190.000,00 in each of *Al día*,
*1–30*, *31–60*, *61–90* and *Más de 90 días* and $ 5.950.000,00 in *Total*; the last row, *Total*, repeats the sums.

**REP-04 · The drill-down lists the documents and links to each invoice**
Smoke: `e2e/reports.spec.ts`.
A client with two open invoices (one 45 days late) › **Ver documentos de Ana Ltda.** › the invoice number.
**Expected:** the client's name as the title; each document with fecha, vencimiento, "45 días vencida" (coloured, and
"Al día" for the other), valor and saldo, the soonest due first, and a total; **Volver a la cartera** returns to the
same date; the invoice number opens the factura de venta.

**REP-05 · Search by tercero and the Al corte date**
Smoke: `e2e/reports.spec.ts`; by hand: pagination (more than 25 clients).
Two clients › search `beto`, then `zzz`, then clear it › **Al corte** `01/01/2020`.
**Expected:** only matching terceros (name or NIT, matched literally: `%` finds nothing), the totals row follows the
search; "Ningún tercero coincide con la búsqueda."; for a date before the invoices, "Ningún cliente te debe nada a esta
fecha."; the date, the search and the page are in the address, so a reload keeps them. A date before an invoice's
date shows what was owed then (a collection made later still counts as owed).

**REP-06 · Cartera de proveedores**
Smoke: `e2e/reports.spec.ts`.
Two suppliers with payables due 5 days ago and in 20 days › **Cartera de proveedores**.
**Expected:** the same layout for what the company owes; the payable is the net of retenciones ($ 1.150.000,00);
**Ver documentos** opens the supplier's payables with a link to each factura de compra.

**REP-07 · The cartera total is the 1305 balance of the books**
Smoke: `e2e/reports.spec.ts` (against the balance de prueba's API); by hand: Contabilidad › Balance de prueba.
Two invoices for a client and a partial receipt › the client's total in Reportes › Contabilidad › Balance de prueba
(today) row 1305; the same with a supplier and 2205.
**Expected:** both figures are equal; after voiding the receipt both grow by its amount; a client with its own
receivable account (130510…) is still inside the 1305 total.

**REP-08 · Cartera exports to CSV that Excel opens**
Smoke: `e2e/reports.spec.ts`; by hand: open the file in Excel.
A client named `Ñandú Ltda.` with an open invoice › **CSV** on *Cartera de clientes*.
**Expected:** the file `cartera-clientes-resumen-<fecha>.csv` opens with the accents right (Ñandú, Identificación,
Más de 90 días), in separate columns, amounts like `1190000.00` (decimal point, no thousands separator), a *Total* row
last. With the search or date set on the screen, the file has the same filter. On Exportar, the "por documento" file has
one row per open invoice with its días de vencida.

**REP-09 · Cartera exports to a PDF with the company frame**
Smoke: `e2e/reports.spec.ts`; by hand: read the PDF.
**PDF** on *Cartera de clientes* and on *Cartera de proveedores*.
**Expected:** the company's logo, razón social, NIT-DV and contact, the title, "Al <fecha>", the table with money as $
1.190.000,00 and dates as DD/MM/AAAA, and the totals row; the browser downloads it as a file.

**REP-10 · Exportar offers every report, with the chosen dates**
Smoke: `e2e/reports.spec.ts`.
**Reportes** › **Exportar**.
**Expected:** CSV and PDF buttons for cartera de clientes and de proveedores (summary and por documento), Libro diario,
Balance de prueba, Estado de resultados and Balance general; changing *Desde*, *Hasta* or *Fecha de corte* changes the
buttons' addresses (`from=`, `to=`, `as_of=`, `date=`); the CSV note explains the format.

**REP-11 · The ledger books download as CSV and PDF**
Smoke: `e2e/reports.spec.ts` (the files); by hand: compare each with its screen in Contabilidad.
An invoice, a receipt › Exportar › each of the four books, CSV and PDF.
**Expected:** the libro diario has one row per entry line (fecha, asiento, documento, cuenta, tercero, descripción,
débito, crédito) and equal totals; the balance de prueba, the estado de resultados and the balance general show the
same figures as their screens; the PDFs carry the company's header.

**REP-12 · A report with nothing to show says so**
Smoke: `e2e/reports.spec.ts`.
A company without invoices › both carteras.
**Expected:** "Ningún cliente te debe nada a esta fecha." and "No le debes nada a ningún proveedor a esta fecha."

**REP-13 · A tercero with nothing owed has no drill-down**
Smoke: `e2e/reports.spec.ts`.
Open `/reportes/clientes/<a tercero of another company, or one that owes nothing>`.
**Expected:** "Este tercero no tiene saldos abiertos a esa fecha." with the way back; nothing of another company shows.

**REP-14 · A billing user sees cartera but not the books**
Signed in as a billing user (section 1).
**Expected:** the dashboard shows cartera and the month's figures and the quick links, but no *Caja y bancos*; Reportes
opens and both carteras export; **Exportar** lists only the carteras and says the books are for the administrator and
the accountant; typing the libro diario export address answers 403.

**REP-15 · The accountant sees the books' figures and exports them**
Signed in as the accountant (section 1).
**Expected:** the dashboard shows *Caja y bancos*; **Exportar** includes the four books; the accountant also has the
quick links to create documents (decided 2026-10-04).

**REP-16 · The dashboard warns when the resolution is running out**
Configuración › Resolución with 12 numbers left (or a *hasta* date in 9 days) › **Tablero**; then an expired one.
**Expected:** "Tu resolución de facturación se está agotando: te quedan 12 números y 9 días de vigencia." with **Ir a la
resolución** (owner only); for an expired or exhausted one, the message says to register the new one; nothing for a user
who cannot create invoices.

**REP-17 · Receipts, payments and voids move cartera**
A receipt for part of an invoice, then another for the rest, then void the first › Reportes after each step; the same
for a supplier payment.
**Expected:** the balance falls by each receipt, the invoice leaves cartera when paid in full, and a voided receipt
puts its amount back; a voided invoice is not in cartera; the dashboard matches.

**REP-18 · A report too big to export is refused, not cut short**
With a company of more than 25.000 entries (or by the API tests): export the libro diario as CSV and as PDF.
**Expected:** clicking the button shows, above the table, "… tiene demasiadas filas para una sola descarga (hasta
50.000 en CSV y 1.500 en PDF). Acota las fechas o los filtros…" and downloads nothing (the screen first rehearses the
export with `check=1`; the API answers 422 `export_too_large`); narrowing the dates downloads it. A CSV streams: the
browser starts saving at once.

**REP-19 · The screens on a tablet, in both themes**
At 1366×768, 1024×768 and 768×1024, Tema Claro and Oscuro: the dashboard, both carteras with five buckets, the
drill-down, Exportar.
**Expected:** the tiles wrap; the cartera table scrolls inside its frame on a narrow screen with the totals row kept;
the late colours (green, amber, red) read in both themes; the CSV and PDF buttons stay visible; the console has no
errors.

