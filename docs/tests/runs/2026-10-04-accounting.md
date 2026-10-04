# UI regression run — 2026-10-04 (accounting)

- **Suite:** [`../ui-regression.md`](../ui-regression.md), at `a271dac`.
- **Branch:** `feature/accounting` at `a271dac`, on this checkout's stack (app <!-- URL -->).
- **Data:** <!-- reset to the seed before the run / not reset (why) -->
- **How:** the smoke suite first (Playwright against this stack), until it is green; then the cases left for a person, in a browser driven through the real UI; emails read in the mail catcher; database/logs where a screen could not prove it.

## Summary

| | Cases |
|---|---|
| Cases in the suite | 234 |
| Run by the smoke suite | 135 |
| Left for the manual run | 99 |
| Manual: pass | 97 (none needed a fix during the run) |
| Manual: fail | 0 |
| Manual: not run by hand | 2: ACC-18 (two idle hours) and REP-18 (over 1.500 rows), both covered by automated tests |
| Findings | 9 (M1–M9): 7 Low, M8 a product decision, M6 seen again in DOC-06; none blocks the release |

## Smoke suite

`python3 ~/.claude/skills/symfony-react-app/scripts/smoke.py` runs it and adds a row here. A run that is not green
is recorded too: write what failed under "Smoke findings", fix it, and run again. The manual run starts only when
the last row is green.

| # | When | Commit | Result | Failed |
|---|---|---|---|---|
| 1 | 2026-10-04 01:56 | `a271dac` | Green: 142 passed, 0 failed | — |
<!-- smoke.py adds a row per run of the whole suite -->

### Smoke findings

<!-- Numbered: the failing test (its case ID), whether the app or the test was wrong, the cause, and the fix (commit). -->

## Manual run

Replace "Not run" with Pass, Fail, "Pass after fix" (with the commit) or Blocked (with why). Group consecutive
passes into ranges (`AREA-01 – 05`) once done.

| ID | Result | Case / notes |
|---|---|---|
| ACC-12 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | The Usuarios tab and its modals on a tablet and in both themes |
| ACC-14 | Pass (Chrome): the owner made Luis Contador; his next click showed the sign-in page; signed in again he returned to Terceros, sidebar "Contador", Configuración in the menu, write actions shown (§8 as changed 2026-10-04) | A changed role takes effect at once |
| ACC-17 | Pass (Mailpit): both e-mails in Spanish with company, inviter, role, expiry 11/10/2026 / one hour and other sessions closing; token after #, this stack's address. Finding M5 | The two e-mails read well |
| ACC-18 | Not run by hand (needs two idle hours); covered by PHPUnit SessionExpiryTest with the clock | A session idle for two hours ends |
| ACC-90 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | The layout on a tablet and in both themes |
| CO-10 | Pass (Chrome, as the accountant): Empresa and Resolución read-only with "Solo el propietario puede cambiar…", no Guardar; the resolution status still shown. Billing: PHPUnit (403 on every write) | The accountant and billing read, only the owner writes |
| CO-18 | Pass (Chrome, owner): after FE-1, Prefijo and Desde disabled with the explanation; Consecutivo actual 2; Hasta lowered to 1 (the last used) is accepted and the page then says the numbers ran out | Once invoices are numbered, desde and the prefix are locked |
| CO-19 | Pass: emitting with Hasta = last used is refused in the Emitir dialog ("ya no tiene números disponibles", Chrome) and dated before the resolution's start with resolution_inactive (API); the invoice stays a draft and the internal consecutive is still 2 (none consumed). Range restored to 5000 afterwards | Emission is refused outside the resolution |
| LED-07 | Pass (Chrome): the diario lists the three demo entries and the real FE-1, FC-1, RC-1, RP-1, each balanced, débitos/créditos right-aligned; "2408" keeps only the four entries that touch it | The libro diario shows each entry with its lines |
| LED-08 | Pass (Chrome): Débitos y créditos cuadran ($ 4.638.000,00 both sides); crédito balances negative; "Ver asientos de 2408" opens the diario filtered by 2408 and the same period | The balance de prueba balances and drills down to the diario |
| LED-09 | Pass (Chrome): ingresos $ 2.000.000, gastos $ 1.200.000, utilidad $ 800.000; balance general "Activo = pasivo + patrimonio" (1.650.000 = 850.000 + 800.000), resultado del ejercicio $ 800.000; every figure checked by hand against the entries | The estado de resultados and the balance general agree |
| LED-10 | Pass (headless screenshots of every Configuración tab at four sizes/themes; Chrome for the taxes and payment-method modals): same intro line, filter, table and Acciones column; no raw key. Finding M3 (hidden tab scroll) | The settings tabs read well next to their siblings |
| LED-11 | Pass: the accountant opens Libros contables and reads/exports the books (Chrome as Luis-Contador; API journal 200, ledger export 204); the billing user sees "Los libros contables son del administrador y del contador." (Chrome) and gets 403 on the journal, the lock date and the ledger exports (API) | Only the owner and the accountant keep the books |
| LED-12 | Pass (Chrome + API): FE-2 emitted half/half posts Dr caja and 13050501 (with the client), Cr 413595 and 240805, balanced (FE-1 checked the same in LED-07); voided with a reason → a second entry, the exact mirror, dated 04/10/2026; the PDF prints "ANULADA el 04/10/2026. Motivo: …". The lock-date refusal was not tried by hand (it would block the rest of the run on today's date); PHPUnit AC-9 proves it | An emitted document posts to the ledger |
| TAX-11 | Pass (API + Chrome): the seeded taxes point at 240805/240810 (IVA), 135515/2365xx (ReteFuente by concept), 135517/236701 (ReteIVA); the account picker completes a typed code to its label and refuses text that is not a postable account (seen in TAX-16) | A tax posts to the accounts the accountant chooses |
| TAX-12 | Pass (Chrome): IVA 19 % and ReteFuente servicios 4 % (used by FE-1/FC-1) and Efectivo, Crédito, Transferencia show Editar and Desactivar but no Eliminar; unused ones still offer Eliminar | A tax or payment method a document used cannot be deleted |
| TAX-13 | Pass (Chrome as Luis-Facturación, address typed): the taxes list shows with no "Nuevo impuesto" and no Acciones column; the billing user has no Configuración in the menu | A billing user only reads |
| TAX-16 | Pass (Chrome): "1110" (a group) is refused with "Elige una cuenta de la lista."; 11100501 completes to "11100501 · BANCOS MONEDA NACIONAL" and Bancolombia is created (11100502 of the case does not exist in the seeded chart: 11100501 used). Finding M6 | A contado method is created on a postable account |
| TAX-19 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | The layout on a tablet and in both themes |
| TER-10 | Pass (API): GET …/export downloads a JSON attachment with every field, contacts and the export date; audit_log has tercero.personal_data_exported. The file is named tercero-<id>.json, not tercero-<número>.json as the case said (harmless) | The personal data is exported as JSON |
| TER-11 | Pass (Chrome): Cuenta por cobrar lists only 1305xx, Cuenta por pagar only 2205xx/2335xx, each starting with "Predeterminada (según las reglas de contabilización)". Saving is proven by PHPUnit | The accounting accounts of a tercero |
| TER-12 | Pass (Chrome): Eliminar on Distribuciones Andina → the dialog stays with "Hay documentos con este tercero: no se puede eliminar, solo desactivar." | A tercero a document uses cannot be deleted, only deactivated |
| TER-13 | Pass (Chrome): in a new factura de venta, "+ Crear nuevo" → the modal (role Cliente ticked, DV computed 7); a NIT already used is refused next to the number; a new one closes the modal and selects "Cliente Rápido S.A.S." in the invoice | Quick-create from a document |
| TER-14 | Pass (Chrome, as the accountant in ACC-14): Nuevo tercero, Editar, Desactivar and Eliminar shown (case rewritten for the 2026-10-04 rule) | The accountant writes terceros like billing (rule changed 2026-10-04)|
| TER-15 | Pass (API as Luis-Facturación): every write flow run as the user through the API (tercero create/deactivate/reactivate/delete; invoice draft/save/emit/void; receipt create/send/void; quotation create/emit/send/convert; purchase emit/void; payment create/send/void): all 2xx | A billing user creates and edits terceros |
| TER-16 | Pass (API): a second company gets 404 on company A's tercero and lists 0 rows | Another company's terceros do not exist |
| TER-17 | Pass (API): "100%" finds "Banco 100% Fiable"; "%" finds only that name (literal, not a wildcard: 1 of 29); "_" finds nothing. Case text corrected | The search treats % and _ as plain characters |
| TER-18 | Pass (API): 29 terceros → 25 per page, total 29; page 2 keeps the role filter | Pagination of a long list |
| TER-19 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | The layout on a tablet and in both themes |
| PRD-12 | Pass (API, test company): 119000 incl. IVA 19 % → net 100000.0000, 50000 → 42016.8067; one-unit lines total 119.000,00 and 50.000,00 (42.016,81 + 7.983,19). Chrome: the list reads "IVA incluido · base $ 113.333,33" for LIC-01. Impoconsumo por valor not checked (M7) | A price that includes IVA carries the net value for the line |
| PRD-13 | Pass (API, test company): with default IVA 19 % and ReteFuente servicios 4 % a product created without taxes takes both; an edit with no taxes clears them | A new product takes the company's default taxes |
| PRD-14 | Pass (Chrome): text that is not an account shows "Elige una cuenta de la lista o borra el campo."; 4135 lists the 4135xx accounts; typing 413595 completes the label; saved ("Producto guardado.") with 413595 · VENTA DE OTROS PRODUCTOS | Revenue and expense accounts are picked from the chart |
| PRD-15 | Pass (Chrome): "Licencia anual" › + Crear nuevo › LIC-01, 119000, IVA incluido, IVA 19 % › the line became LIC-01 with Valor unitario 100.000 and total $ 119.000,00; LIC-01 is in Productos y servicios | Quick-create from a document line |
| PRD-16 | Pass (Chrome): code SRV-01 shows "Ya hay un producto o servicio con este código." under Código, empty Nombre "Este campo es obligatorio.", both inside the modal; Escape closed it and the line kept its text | Quick-create refuses what the full form refuses |
| PRD-17 | Pass (API, test company): deleting a product used by FE-6 is refused (409, "deactivate it instead"); deactivated, it is not offered by the active search and FE-6 still shows "SRV-01 · Consultoría" | A product used by a document is only deactivated |
| PRD-18 | Pass (API as Marta-Contador): product create 201, edit, category create, deactivate and delete (no documents) all succeed |
| PRD-19 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | The layout on a tablet and in both themes |
| DOC-01 | Pass (Chrome, by-hand part): three lines of 1 × 0,3333 with IVA 19 % give Total bruto $ 1,00 and Impuestos $ 0,19, Total neto $ 1,19 (rounded once). The PRD example: SAL-20 | The totals preview shows the PRD example as the server computes it — by hand: the three `0.3333` lines |
| DOC-02 | Pass (Chrome): "Di" shows "Escribe al menos 3 caracteres." and no list; "Dis" lists Distribuciones Andina S.A.S. NIT 860001022-7; Contacto offered Ana Pérez; choosing another client set Contacto back to "Sin contacto" | The tercero is searched from the third character and brings its contacts — by hand: choosing another client empties Contacto |
| DOC-05 | Pass (Chrome): the dialog shows "Subtotal de la línea $ 100.000,00"; IVA 5 % with the tick changed the line and LIC-01 (API: charge tax IVA 5 %); a new LIC-01 line came with IVA 5 %. LIC-01's price includes IVA, so the new line's net unit price is 113.333,3333 (119.000 total), as designed for IVA-inclusive prices. "Without the tick" not repeated by hand (smoke) | The tax dialog changes a line and, when asked, the product from now on — by hand: the product in Productos y servicios, and without the tick |
| DOC-06 | Pass (Chrome): Cuenta de gasto › 5135 lists 513505 … 513599; 513525, 1 × 500.000, IVA 19 % totals $ 595.000,00; "cuenta inventada" › Guardar borrador: "Elige una cuenta de la lista."; switching to Producto and back empties the account (the old error stays, as in M6) | A purchase line goes straight to an expense account |
| DOC-07 | Pass (Chrome): the first forma de pago offered the whole total with "Coincide con el total neto"; at 100.000 "Faltan $ 124.000,79 para el total neto"; Crédito offered the rest with A 30 días and a date 30 days on; Otra fecha shows a date field; moving Fecha de elaboración to 01/10/2026 left the typed date and A 30 días then read 31/10/2026. A quotation has no Formas de pago. Guardar on a draft does not check the sum (only Emitir does; its message is in the smoke run) | Formas de pago add up to Total neto, with a due date on crédito — by hand: *Otra fecha* and moving Fecha de elaboración |
| DOC-08 | Pass (Chrome): Enter on the last line added a line with the cursor in Producto/Servicio; Alt + ↑ moved line 2 up with the cursor; Enter on another line went down to the same cell; in a search ↓/↑ walked the list and Escape closed it; nothing was submitted | The lines work from the keyboard — by hand: the arrows, Enter and Escape in a product search |
| DOC-09 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | The form on a tablet, in both themes, and read-only |
| SAL-09 | Pass (PDF rendered and read): logo, razón social, NIT-DV, the resolution text ("Resolución DIAN No. … del 05/08/2026, vigente hasta el 31/07/2027. Autoriza la numeración del FE-1 al FE-5000."), No. FE-1, the client's name, NIT, e-mail and responsabilidades, the lines, Total bruto, IVA 19 % with its base, Total neto, formas de pago, observaciones. Finding M8 (the title says "electrónica") | The PDF carries what an invoice must say — by hand: what it shows |
| SAL-11 | Pass (pdftotext): the voided FE-2 prints "ANULADA el 04/10/2026. Motivo: Factura duplicada" (LED-12) | The PDF of a voided invoice says ANULADA (AC-7) |
| SAL-12 | Pass (API, test company) + Chrome: send FE-1 → 202 and Mailpit got "Factura de venta FE-1 de …" with factura-FE-1.pdf; a draft → 409. Chrome: only the emitted invoice has Enviar, drafts and voided ones do not | An invoice is sent again from the list |
| SAL-13 | Pass (API, test company): a client from the full form without e-mail › emit-and-send → 422 tercero_has_no_email (Spanish text in salesInvoice.json), the invoice stays a draft, Emitir numbers it | A client without e-mail cannot be emitted and sent |
| SAL-14 | Pass (API, test company): resolution 1–2, FE-1 and FE-2 emitted, the third → 422 resolution_exhausted, still a draft; the resolution reads exhausted, 0 left | Nothing is emitted past the resolution's hasta (AC-8) |
| SAL-15 | Pass (API, test company): dated before the resolution → 422 (resolution not valid on the date); tomorrow → 422 (future date); with the lock date yesterday, dated yesterday → 409 period_locked; each time a draft, no entry added; dated a week ago it was emitted (FE-3) on that date | An invoice dated outside the resolution, in the future or in a closed period is not emitted (AC-8, AC-9) |
| SAL-16 | Pass (API, test company): lock date today › void an emitted invoice → 409 period_locked ("La fecha está en un periodo contable cerrado (fecha de bloqueo)."), status unchanged | A void today in a closed period is refused (AC-9) |
| SAL-17 | Pass (API, test company): the client of a draft deactivated › emit → 422 tercero_inactive; the active-client search no longer finds it | An inactive client is not invoiced |
| SAL-18 | Pass (API, test company): a saved draft changed (Cantidad 3, a line added, one forma de pago removed) and read back exactly as saved, totals 4.620.000,00, no number. Chrome: "Borrador guardado." on save | A draft is edited and saved again |
| SAL-19 | Pass (API, test company): a crédito due date before the invoice date → violation payments.0.due_date "El vencimiento no puede ser anterior a la fecha de la factura."; nothing saved | The server's checks show next to their fields |
| SAL-20 | Pass (API, test company): SRV-01 × 2, 10 % off, ReteFuente 4 %, crédito: Total neto 2.070.000,00; Dr 13050501 2.070.000, Dr 417501 200.000, Dr 135515 72.000, Cr 413595 2.000.000, Cr 240805 342.000 (2.342.000 each side) | Discounts and retenciones are posted as A.1 |
| SAL-21 | Pass (API, test company): with Cuenta de ingreso 415595 the invoice credits 415595 1.000.000 (not 413595) | A product's own revenue account is credited |
| SAL-22 | Pass (API as Marta-Contador): every write flow run as the user through the API (tercero create/deactivate/reactivate/delete; invoice draft/save/emit/void; receipt create/send/void; quotation create/emit/send/convert; purchase emit/void; payment create/send/void): all 2xx | The accountant writes invoices like billing (rule changed 2026-10-04)|
| SAL-23 | Pass (API as Luis-Facturación): every write flow run as the user through the API (tercero create/deactivate/reactivate/delete; invoice draft/save/emit/void; receipt create/send/void; quotation create/emit/send/convert; purchase emit/void; payment create/send/void): all 2xx | A billing user invoices |
| SAL-24 | Pass (API, test company): FE-1 with RC-1 for 1.000.000 is partially_paid and its void → 409 ("Esta factura tiene recibos de caja aplicados: anúlalos primero."); after voiding the receipts it is Emitida and the void posts the reversing entry | An invoice with a receipt applied is not voided |
| SAL-25 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | The screens on a tablet, in both themes |
| PUR-11 | Pass with finding M9 (PDF rendered): header with logo and NIT-DV, "Factura de compra No. FC-1", supplier name and its invoice number FAC-881, dates, status, the line with IVA and ReteFuente, formas de pago, totals. The supplier's NIT is missing | The PDF of an emitted invoice downloads — by hand: see the case |
| PUR-13 | Pass (API, test company): a producto with 519530, a producto without an account, a servicio without; Efectivo: Dr 519530, Dr 620501, Dr 519595 100.000 each, Cr 11050501 300.000; no payable | Lines by product post to the product's account, 6205 or gasto por defecto |
| PUR-14 | Pass (API, test company): 2 × 500.000 −10 % IVA 19 % and 100.000 Impoconsumo 8 % on 513525: Dr 513525 1.008.000 (900.000 + 108.000), Dr 240810 171.000, Cr 11050501 1.179.000 | A discount is netted on the line and impoconsumo is part of the cost |
| PUR-15 | Pass (API, test company): lock date yesterday › a draft dated yesterday → 409 period_locked, still a draft, the next emission took the next number (FC-8 after FC-7); dated tomorrow → 422 issue_date_in_future | No purchase is emitted on or before the fecha de bloqueo |
| PUR-16 | Pass (API, test company): supplier deactivated › emit → 422 supplier_inactive ("El proveedor está inactivo: reactívalo para emitir.") | An inactive supplier cannot be emitted to |
| PUR-17 | Pass (API, test company) + Chrome: 1105 offers nothing, 5135 lists 513505 … 513599; caja 11050501 on a line → "Elige una cuenta de gasto o costo activa que se pueda usar en compras." An account marked "usable en compras" not checked by hand (PHPUnit) | Only accounts usable on purchases are offered on a line |
| PUR-18 | Pass (API, test company): FC on crédito 1.190.000, RP 400.000 → partially_paid, saldo 790.000, void → 409 document_has_allocations; paying the rest → paid | A supplier on credit owes the net amount, paid down by its payments |
| PUR-19 | Pass (API as Marta-Contador): every write flow run as the user through the API (tercero create/deactivate/reactivate/delete; invoice draft/save/emit/void; receipt create/send/void; quotation create/emit/send/convert; purchase emit/void; payment create/send/void): all 2xx | The accountant writes purchases like billing (rule changed 2026-10-04)|
| PUR-20 | Pass (API, test company): the demo owner asking for the test company's invoice and its PDF → 404 for both | Another company's invoice is not found |
| PUR-21 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | The screens at laptop and tablet widths, light and dark |
| COT-13 | Pass (PDF rendered): header and logo, "Cotización No. C-1", client fiscal data, Fecha de elaboración and Válida hasta, the Encabezado above the lines, lines and totals in Colombian format, Condiciones comerciales | The PDF reads like a quotation |
| COT-14 | Pass (Chrome): Responsable lists "Sin responsable" and the active employee only (an inactive one is left out); moving the date to 01/10 set Válida hasta 31/10; typed 15/10 stayed when the date moved to 03/10; 01/10 › Guardar: "La oferta no puede vencer antes de la fecha de la cotización." under the field | The responsable is an employee and the vencimiento follows the date |
| COT-15 | Pass (API, test company): a draft's header, terms and quantity changed and read back; after Emitir a second emit and an edit → 409 document_not_draft | A draft is edited, an emitted quotation is not |
| COT-16 | Pass (API, test company): a second convert → 409 already converted, one invoice only; a quotation accepted by hand converted once, a second convert 409 | A quotation converts only once |
| COT-17 | Pass (API): billing and accountant both create, emit, send and convert quotations | Roles (rule changed 2026-10-04)|
| COT-18 | Pass (API, test company): the converted draft with Efectivo for the total emitted (FE-6) and posted Dr 11050501 2.380.000, Cr 413595 2.000.000, Cr 240805 380.000; the quotation stays accepted with converted_invoice_id | The accepted draft invoice is emitted like any other |
| COT-19 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | Layout |
| RC-08 | Pass (PDF rendered): "Recibo de caja No. RC-2", client with NIT, date, where the money came in, the invoice paid and the amount, Total recibido, a signature line; voided: ANULADA across the page and "ANULADO el 04/10/2026. Motivo: …" | The PDF reads as a recibo de caja, and ANULADA once voided |
| RC-09 | Pass (API, test company): a client with only cash invoices has no open receivables; two full receipts for one receivable: the first RC-3, the second → 422 allocation_exceeds_balance; no open rows left | A client with nothing owed, and a refusal from the server |
| RC-10 | Pass (API, test company): lock date yesterday › a receipt dated yesterday → 409 period_locked, not numbered (RC-7 → RC-8 today); with the lock date today, Anular → 409 | Nothing is received on or before the lock date (AC-9) |
| RC-11 | Pass (API, test company): see SAL-24 (partially_paid, void refused until the receipt is voided) | An invoice with a receipt applied is voided only after the receipt |
| RC-12 | Pass (API as Marta-Contador): every write flow run as the user through the API (tercero create/deactivate/reactivate/delete; invoice draft/save/emit/void; receipt create/send/void; quotation create/emit/send/convert; purchase emit/void; payment create/send/void): all 2xx | The accountant receives and voids like billing (rule changed 2026-10-04)|
| RC-13 | Pass (API as Luis-Facturación): every write flow run as the user through the API (tercero create/deactivate/reactivate/delete; invoice draft/save/emit/void; receipt create/send/void; quotation create/emit/send/convert; purchase emit/void; payment create/send/void): all 2xx | A billing user receives |
| RC-14 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | The screens on a tablet, in both themes |
| PAY-08 | Pass (PDF rendered): "Recibo de pago No. RP-2", supplier with NIT, date, where the money left from, the invoice paid, Total pagado, signature line; voided: ANULADA across and the reason | The PDF reads as a recibo de pago, and ANULADA once voided |
| PAY-09 | Pass (API, test company): a supplier with only cash invoices has no open payables; a second payment over the saldo → 422 ("790000.00 is more than the 0.00 available") | A supplier with nothing owed, and a refusal from the server |
| PAY-10 | Pass (API, test company): lock date yesterday › a payment dated yesterday → 409 period_locked, not numbered (RP-6 → RP-7 today); lock today, Anular → 409 | Nothing is paid on or before the lock date (AC-9) |
| PAY-11 | Pass (API, test company): see PUR-18; after voiding both payments the invoice is Emitida and its void is accepted | An invoice with a payment applied is voided only after the payment |
| PAY-12 | Pass (API as Marta-Contador): every write flow run as the user through the API (tercero create/deactivate/reactivate/delete; invoice draft/save/emit/void; receipt create/send/void; quotation create/emit/send/convert; purchase emit/void; payment create/send/void): all 2xx | The accountant pays and voids like billing |
| PAY-13 | Pass (API as Luis-Facturación): every write flow run as the user through the API (tercero create/deactivate/reactivate/delete; invoice draft/save/emit/void; receipt create/send/void; quotation create/emit/send/convert; purchase emit/void; payment create/send/void): all 2xx | A billing user pays |
| PAY-14 | Pass (API + Chrome): proveedor search "Ser" lists only Servicios Técnicos del Valle S.A.S. NIT 830054539-0 (a client-only "Servicios Cliente" is left out, API); "Se" → "Escribe al menos 3 caracteres."; "Serzzqx" → "Ningún proveedor coincide." | The supplier search only offers suppliers |
| PAY-15 | Pass (Chrome): Nuevo recibo de caja › Distribuciones Andina: FE-1 with saldo $ 295.000,00; Pagar todo and Valor recibido 295.000 → "Cuadra: el valor aplicado es igual al valor recibido." (the payment form reads "Valor pagado") | Cash receipts are unchanged by the shared allocation table |
| PAY-16 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | The screens on a tablet, in both themes |
| REP-14 | Pass (Chrome as Luis-Facturación): dashboard without Caja y bancos, cartera and month figures and quick links; Exportar lists only the four cartera reports and says the books are for the owner and the accountant; API: cartera export 204, ledger export 403 | A billing user sees cartera but not the books |
| REP-15 | Pass (API as Marta, accountant): dashboard cash_and_banks 1.355.000; the four books export as CSV and PDF (200 each); the session carries WRITE_DOCUMENTS, which shows the quick links | The accountant sees the books' figures and exports them |
| REP-16 | Pass (Chrome): 12 numbers and 9 days left → "Tu resolución de facturación se está agotando: te quedan 12 números y 9 días de vigencia." with "Ir a la resolución"; exhausted → "Se acabaron los números de tu resolución de facturación. Registra una nueva para seguir facturando." (every role can now create invoices, so the "nothing for a user who cannot" part has no user) | The dashboard warns when the resolution is running out |
| REP-17 | Pass (API, test company): cartera de clientes and the dashboard 2.070.000 → 1.070.000 after RC-1 → 0 (left cartera) after RC-2 → 1.000.000 after voiding RC-1 → a voided invoice is not listed; the same for suppliers 1.190.000 → 790.000 → 0 → 400.000 → 0 | Receipts, payments and voids move cartera |
| REP-18 | Not run by hand (needs over 1.500 PDF or 50.000 CSV rows); covered by ReportExportApiTest (422 export_too_large, check=1) and ReportTable.test.tsx (the message, nothing downloaded) | A report too big to export is refused, not cut short |
| REP-19 | Pass (headless Chromium at 1024×768 and 768×1024, light and dark; screenshots judged by eye; no page overflows sideways; at 768 the drawer opens with ☰ and closes on Escape). Findings M1–M3 | The screens on a tablet, in both themes |

## Findings

M1 · Low · RC-14 (and the other lists with many columns): at 1024×768 the receipts list's Acciones column is past the
table's edge; the table scrolls sideways inside its box (nothing is lost), but a tablet in landscape should see every
column. Fix: compact the columns or wrap Acciones, as the document editor did (F8).

M2 · Low · DOC-09/SAL-25: at 768×1024 the document editor's line headers "Descripción" and "Cantidad" run together and
the tax selects are cut ("Sin impues…"); the grid scrolls inside its box below 960 px by design (F8).

M3 · Low · ACC-12/LED-10: the Configuración tab bar scrolls sideways with its scrollbar hidden, so at 768 nothing says
more tabs exist ("Plan de cuent…" is cut). Fix: a fade or a visible scroll cue at the edge.


<!-- Numbered: what happened, which case, the cause, and the fix (commit) or why it was left. -->

M4 · Low · The browser tab's title stays from the previous page on screens without their own (the dashboard after
the invitation page read "Aceptar invitación · Mustang"; Terceros after sign-in read "Ingresar · Mustang"). Fix: every
page sets its title, or the shell resets it to the section's name.

M5 · Low · ACC-17: in the HTML part of the invitation and reset e-mails, "copia este enlace en tu navegador:" runs into
the link with no space.

M6 · Low · TAX-16 (account picker): after a refused value, typing a valid code completes the label but the old error
"Elige una cuenta de la lista." stays under the field until the next save. Seen again in DOC-06: switching a purchase line
to Producto and back empties the account but keeps the error.

M7 · Low · TAX: "Impoconsumo por valor" is seeded at $ 0,00 per unit; a company that sells under it must first set
the value (the accountant edits it). Worth a note in the tab's intro, or seeding it inactive.

M8 · Product decision · SAL-09: the sales invoice PDF is titled "Factura electrónica de venta" while stage 1 transmits
nothing to the DIAN (no CUFE, no XML, no validation). A client could take the PDF for a valid electronic invoice. Options:
title it "Factura de venta" until stage 4, or print a line saying it is not yet a DIAN-validated electronic invoice.

M9 · Low · PUR-11 and the receipt PDFs: the purchase PDF omits the supplier's NIT; its line "Valor total" subtracts the
retención (sales shows subtotal + IVA) and prints quantities as "1,00" (sales "1"); in the receipt and payment PDFs the
"Valor aplicado" header is left-aligned over right-aligned amounts.

## Conditions

- Stack: the `feature/accounting` worktree on Docker at http://localhost:8090, Mailpit at :8035, Chrome (the user's own
  browser, driven through the Claude in Chrome extension) on Linux at 1560×784, dark theme.
- The user's password-manager extension rewrites the sign-in form, so the first sign-in after a page load often failed;
  a retry or Return worked. Not an app bug. Later sign-ins in the run were made with `fetch` to `/api/v1/auth/sign-in`
  in the page, then a reload.
- Clicks sent by the extension sometimes only focused the window; a second click on the same spot did it. Fields that
  did not take a value were re-checked from the DOM before a result was written.
- The layout cases (1024×768 and 768×1024, both themes) ran in headless Chromium (Playwright), because the extension
  could not resize the maximised window; the screenshots were judged by eye.
- Cases about rules, postings and refusals marked "(API, test company)" ran through the API in companies made for the
  run (Regresión 856721910, Resolución 870279795, Aviso 815061956), so the lock date and an exhausted resolution did not
  touch the demo company. The UI copy for each error code was checked in the Spanish locale files; the browser was used
  for every case about the screens themselves.
- The role cases ran through the API as Luis (billing) and Marta (accountant), with a browser glance per role.
- Not run by hand: ACC-18 (PHPUnit SessionExpiryTest with the clock) and REP-18 (ReportExportApiTest and
  ReportTable.test.tsx).
- The demo company was changed by the run: contact Ana Pérez on Distribuciones Andina, product LIC-01 (IVA 5 %,
  income account 413595), employees Valeria Gómez and Pedro Inactivo (inactive), and one draft sales invoice saved from
  the editor cases.
