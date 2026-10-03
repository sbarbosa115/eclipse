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
