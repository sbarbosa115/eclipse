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
| Manual: pass | <!-- N (M after a fix made during the run) --> |
| Manual: fail | <!-- N: IDs --> |

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
| ACC-12 | Not run | The Usuarios tab and its modals on a tablet and in both themes |
| ACC-14 | Not run | A changed role takes effect at once |
| ACC-17 | Not run | The two e-mails read well |
| ACC-18 | Not run | A session idle for two hours ends |
| ACC-90 | Not run | The layout on a tablet and in both themes |
| CO-10 | Not run | The accountant and billing read, only the owner writes |
| CO-18 | Not run | Once invoices are numbered, desde and the prefix are locked |
| CO-19 | Not run | Emission is refused outside the resolution |
| LED-07 | Not run | The libro diario shows each entry with its lines |
| LED-08 | Not run | The balance de prueba balances and drills down to the diario |
| LED-09 | Not run | The estado de resultados and the balance general agree |
| LED-10 | Not run | The settings tabs read well next to their siblings |
| LED-11 | Not run | Only the owner and the accountant keep the books |
| LED-12 | Not run | An emitted document posts to the ledger |
| TAX-11 | Not run | A tax posts to the accounts the accountant chooses |
| TAX-12 | Not run | A tax or payment method a document used cannot be deleted |
| TAX-13 | Not run | A billing user only reads |
| TAX-16 | Not run | A contado method is created on a postable account |
| TAX-19 | Not run | The layout on a tablet and in both themes |
| TER-10 | Not run | The personal data is exported as JSON |
| TER-11 | Not run | The accounting accounts of a tercero |
| TER-12 | Not run | A tercero a document uses cannot be deleted, only deactivated |
| TER-13 | Not run | Quick-create from a document |
| TER-14 | Not run | The accountant reads and cannot change |
| TER-15 | Not run | A billing user creates and edits terceros |
| TER-16 | Not run | Another company's terceros do not exist |
| TER-17 | Not run | The search treats % and _ as plain characters |
| TER-18 | Not run | Pagination of a long list |
| TER-19 | Not run | The layout on a tablet and in both themes |
| PRD-12 | Not run | A price that includes IVA carries the net value for the line |
| PRD-13 | Not run | A new product takes the company's default taxes |
| PRD-14 | Not run | Revenue and expense accounts are picked from the chart |
| PRD-15 | Not run | Quick-create from a document line |
| PRD-16 | Not run | Quick-create refuses what the full form refuses |
| PRD-17 | Not run | A product used by a document is only deactivated |
| PRD-18 | Not run | The accountant only reads |
| PRD-19 | Not run | The layout on a tablet and in both themes |
| DOC-01 | Not run | The totals preview shows the PRD example as the server computes it — by hand: the three `0.3333` lines |
| DOC-02 | Not run | The tercero is searched from the third character and brings its contacts — by hand: choosing another client empties Contacto |
| DOC-05 | Not run | The tax dialog changes a line and, when asked, the product from now on — by hand: the product in Productos y servicios, and without the tick |
| DOC-06 | Not run | A purchase line goes straight to an expense account |
| DOC-07 | Not run | Formas de pago add up to Total neto, with a due date on crédito — by hand: *Otra fecha* and moving Fecha de elaboración |
| DOC-08 | Not run | The lines work from the keyboard — by hand: the arrows, Enter and Escape in a product search |
| DOC-09 | Not run | The form on a tablet, in both themes, and read-only |
| SAL-09 | Not run | The PDF carries what an invoice must say — by hand: what it shows |
| SAL-11 | Not run | The PDF of a voided invoice says ANULADA (AC-7) |
| SAL-12 | Not run | An invoice is sent again from the list |
| SAL-13 | Not run | A client without e-mail cannot be emitted and sent |
| SAL-14 | Not run | Nothing is emitted past the resolution's hasta (AC-8) |
| SAL-15 | Not run | An invoice dated outside the resolution, in the future or in a closed period is not emitted (AC-8, AC-9) |
| SAL-16 | Not run | A void today in a closed period is refused (AC-9) |
| SAL-17 | Not run | An inactive client is not invoiced |
| SAL-18 | Not run | A draft is edited and saved again |
| SAL-19 | Not run | The server's checks show next to their fields |
| SAL-20 | Not run | Discounts and retenciones are posted as A.1 |
| SAL-21 | Not run | A product's own revenue account is credited |
| SAL-22 | Not run | The accountant reads invoices and cannot change them |
| SAL-23 | Not run | A billing user invoices |
| SAL-24 | Not run | An invoice with a receipt applied is not voided |
| SAL-25 | Not run | The screens on a tablet, in both themes |
| PUR-11 | Not run | The PDF of an emitted invoice downloads — by hand: see the case |
| PUR-13 | Not run | Lines by product post to the product's account, 6205 or gasto por defecto |
| PUR-14 | Not run | A discount is netted on the line and impoconsumo is part of the cost |
| PUR-15 | Not run | No purchase is emitted on or before the fecha de bloqueo |
| PUR-16 | Not run | An inactive supplier cannot be emitted to |
| PUR-17 | Not run | Only accounts usable on purchases are offered on a line |
| PUR-18 | Not run | A supplier on credit owes the net amount, paid down by its payments |
| PUR-19 | Not run | The accountant reads purchases but does not change them |
| PUR-20 | Not run | Another company's invoice is not found |
| PUR-21 | Not run | The screens at laptop and tablet widths, light and dark |
| COT-13 | Not run | The PDF reads like a quotation |
| COT-14 | Not run | The responsable is an employee and the vencimiento follows the date |
| COT-15 | Not run | A draft is edited, an emitted quotation is not |
| COT-16 | Not run | A quotation converts only once |
| COT-17 | Not run | Roles |
| COT-18 | Not run | The accepted draft invoice is emitted like any other |
| COT-19 | Not run | Layout |
| RC-08 | Not run | The PDF reads as a recibo de caja, and ANULADA once voided |
| RC-09 | Not run | A client with nothing owed, and a refusal from the server |
| RC-10 | Not run | Nothing is received on or before the lock date (AC-9) |
| RC-11 | Not run | An invoice with a receipt applied is voided only after the receipt |
| RC-12 | Not run | The accountant reads receipts and cannot change them |
| RC-13 | Not run | A billing user receives |
| RC-14 | Not run | The screens on a tablet, in both themes |
| PAY-08 | Not run | The PDF reads as a recibo de pago, and ANULADA once voided |
| PAY-09 | Not run | A supplier with nothing owed, and a refusal from the server |
| PAY-10 | Not run | Nothing is paid on or before the lock date (AC-9) |
| PAY-11 | Not run | An invoice with a payment applied is voided only after the payment |
| PAY-12 | Not run | The accountant pays and voids like billing |
| PAY-13 | Not run | A billing user pays |
| PAY-14 | Not run | The supplier search only offers suppliers |
| PAY-15 | Not run | Cash receipts are unchanged by the shared allocation table |
| PAY-16 | Not run | The screens on a tablet, in both themes |
| REP-14 | Not run | A billing user sees cartera but not the books |
| REP-15 | Not run | The accountant sees the books' figures and exports them |
| REP-16 | Not run | The dashboard warns when the resolution is running out |
| REP-17 | Not run | Receipts, payments and voids move cartera |
| REP-18 | Not run | A report too big to export is refused, not cut short |
| REP-19 | Not run | The screens on a tablet, in both themes |

## Findings

<!-- Numbered: what happened, which case, the cause, and the fix (commit) or why it was left. -->

## Conditions

<!-- Anything about the environment that could have affected the result. -->
