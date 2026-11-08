# Mustang design

How Mustang looks and behaves on screen, and the rules that keep every screen the same. The source of truth is the
code: the kit (`backend/assets/react/shared/ui/`, imported as `@/shared/ui`) and its stylesheet
(`shared/ui/kit/admin.css`). This file explains them; when the two disagree, fix one of them.

## Principles

1. **One kit, no drift.** Every table, button, field, filter, modal and state comes from `@/shared/ui`. A page never
   writes `<table>`, a bare `<button>` or its own modal. If the kit lacks something, add it to the kit.
2. **Calm and dense.** It is an accounting tool used all day: a quiet green-grey palette, small radii, light shadows,
   no decoration. Colour carries meaning (status, kind of action), never ornament.
3. **Colour is never the only signal.** Tinted rows also get a bar down the first cell, a legend and the status in
   words for screen readers; badges have text; icon buttons have a tooltip and an accessible name.
4. **The numbers are exact.** Money is shown as typed by Colombians, aligned and in tabular figures; the UI formats
   money, it never computes what gets posted.
5. **Two themes, one design.** Light and dark are the same design on two palettes; every colour is a token.

## Tokens

All tokens live on `.admin` in `admin.css` and are redefined for `[data-theme='dark']`. **Nothing below the token
blocks names a colour** — page stylesheets use `var(--color-…)` only, with no hex fallbacks.

### Colour

| Token | Light | Dark | Use |
|---|---|---|---|
| `--color-bg` | `#f5f6f3` | `#111513` | Page background; hover fill on quiet controls |
| `--color-surface` | `#ffffff` | `#191e1b` | Cards, tables, sidebar, modals, inputs |
| `--color-surface-sunken` | `#fafbf9` | `#151a17` | Table headers, nested items |
| `--color-border` | `#dfe3dc` | `#2e3631` | Dividers, card and table borders |
| `--color-border-strong` | `#c5ccc2` | `#65706a` | Input borders, dashed empty states |
| `--color-text` | `#1d2521` | `#e4e9e5` | Body text |
| `--color-muted` | `#5f6b64` | `#a1aca5` | Secondary text, labels, table headers |
| `--color-primary` / `-hover` / `-soft` | `#1f5f4a` | `#5cb896` | Brand green: primary buttons, links, active nav and tabs, focus ring |
| `--color-on-primary` | `#ffffff` | `#0c1712` | Text on a primary fill |
| `--color-success` / `-soft` | `#1f6b3a` | `#74cf93` | Done, paid, confirm actions |
| `--color-warning` / `-soft` | `#8a5a00` | `#e6b450` | Waiting on someone, drafts |
| `--color-danger` / `-soft` / `-border` | `#b3261e` | `#f28b82` | Errors, destructive actions, overdue |
| `--color-info` / `-soft` | `#1d4f91` | `#79c2ec` | In progress, edit actions |
| `--color-accent` / `-soft` | `#6b3fa0` | `#cfa4ea` | Partly done, open/view actions |
| `--color-indigo` / `-soft` | `#3a4899` | `#7d84e8` | Setup actions (add to, act as) |
| `--color-neutral` / `-soft` | `#3f4a44` | `#c7cfca` | No status, finished/closed |
| `--color-tooltip` / `--color-on-tooltip` | ink / white | inverted | Icon-button tooltips |
| `--color-overlay` | 45% ink | 60% black | Modal and drawer backdrops |
| `--color-edge` | 6% ink | 35% black | The 1px lift under outlined buttons |

Each hue has a `-soft` partner for fills (badges, alerts, row tints, hover). In dark mode each hue is a lighter tint of
its light self, so "green = confirm" holds in both themes, and surfaces step up in lightness instead of leaning on
shadows.

### Shape, depth, spacing

| Token | Value | Use |
|---|---|---|
| `--radius` | 8px | Cards, tables, modals, empty states |
| `--radius-sm` | 6px | Buttons, inputs, alerts, nav links |
| `999px` | — | Badges, sub-tab pills, switches |
| `--shadow` | 1–3px soft | Cards and tables at rest |
| `--shadow-lg` | 12/32px | Modals, drawer, combobox popups |
| `--gutter` | 16px | Minimum side padding on every width |

Spacing is a 4px rhythm: 4/6/8 inside controls, 12/14/16 between controls and inside cards, 20 for card padding and
section gaps, 28/48 for the page's top and bottom.

### Type

- **Family:** the system stack (`--font`: `system-ui, -apple-system, 'Segoe UI', Roboto, …`). No web font is loaded.
- **Base:** 15px, line-height 1.5.
- **Scale:** `h1` 1.6rem (page title) · `h2` 1.15rem (modal and section titles) · card title 1rem · labels 0.9rem/600
  · hints, errors 0.82rem · `.small` 0.85rem · table headers 0.8rem/600 uppercase with 0.03em tracking · nav section
  titles 0.72rem/600 uppercase.
- **Weights:** 400 body, 500 nav and links, 600 labels, buttons, headers, 700 brand and big figures.
- **Numbers:** a cell holding an amount takes the kit's `.num` class: right-aligned, on one line, in tabular figures so
  the columns line up. Big figures (`.dashboard-figure`) use tabular figures too.

## Layout

- **Shell:** a 248px sticky sidebar (brand, company, nav sections, user and theme picker at the foot) and a content
  column, max 1200px, centred, padded `max(16px, 3vw)`.
- **≤ 1100px (tablets, landscape included):** the sidebar becomes a drawer (min(280px, 85vw)) behind a 56px sticky
  top bar with the menu button; tab bars wrap instead of scrolling.
- **≤ 1439px (laptops):** a row's action buttons go two by two and table headers may wrap, so wide lists fit beside the
  menu.
- **≤ 600px:** form grids drop to one column; filters take the full width.
- **Page anatomy, top to bottom:** `PageHeader` (title, optional subtitle, primary actions right) → `Tabs` (the page's
  views, if any) → `TabIntro` (one line on what the tab is for, its action beside it) → `FilterBar` → `RowLegend` →
  `DataTable` → `Pager`. Forms and details live in `Card`s.
- **Check every screen** at laptop (1280–1440) and tablet (768–1100) widths, in light and dark.

## Components

| Component | What it is, and the rule |
|---|---|
| `ThemedRoot` | The `.admin` root with `data-theme`. Theme choice (`system`/`light`/`dark`) is kept in `localStorage` (`mustang.theme`); `system` follows the device live. |
| `PageHeader` | Title, subtitle, actions. The title block wraps; actions stay right. |
| `Button` | `primary` (one per view: the main thing to do), `secondary` (soft green), `ghost` (Cancel, Previous, Retry), `link` (only text that navigates — never an action). `size="sm"` for 32px. `busy` swaps the label for "Procesando…" and disables it. |
| `ActionButton` / `actionClass` | Worded buttons in an Actions cell or modal, outlined in the colour of what they do (see below). `actionClass` styles a router `Link` the same way. |
| `IconButton` | 32px icon-only action. `label` is required: it is the tooltip and the accessible name. The icon picks its colour (`trash` → danger, `pencil` → edit, `eye` → open, `plus` → setup, `check` → confirm). |
| `Field` | Label above one control; hint or error below, wired with `aria-describedby`/`aria-invalid`. `optional` adds "(opcional)"; required is the default and unmarked. |
| `MoneyInput`, `DateInput`, `SearchCombobox` | Colombian amounts (`1.190.000,50`), day-first dates, and searchable pickers with a "create" row. Use these, never a bare `<input>` for money, dates or entity pickers. |
| `FilterBar` | Search (300ms debounce), then one labelled `<select>` per filter, then the table's primary action pushed to the far right. Controls align on their bottom edge. |
| `DataTable` | Data columns plus an **Actions** column added last, always. `busy` dims it while reloading instead of blanking it. |
| `Row` | A row tinted by status (`status` + `label`): tint, a 4px accent bar on the first cell, `title`, and the status read aloud. Tables have no status column — the tint is the status — so a tinted table always has a `RowLegend`. |
| `Actions` | The last cell. Everything clickable for that row goes here. |
| `Badge` | Status as a pill, in the same tone as its row tint (`toneFor`). |
| `Tabs` / `TabPanel` | The page's views (underlined bar) or, with `sub`, a tab's own views (pills). Tabs switch views; they never filter. Arrow keys move between them. |
| `Modal` / `FormModal` | Max 640px, top-aligned at 5vh. Escape and backdrop click close; focus goes to the first control and returns to the opener. `FormModal`: error alert on top, two-column form grid, **Cancelar** then the submit button at the end. |
| `Card` | A section of a form or a group of details: surface, border, 20px padding, 14px gap. |
| `Alert` | `info`/`success`/`warning`/`error`, soft fill in its hue. Errors are `role="alert"`, the rest `status`. Every save shows one, and it belongs to that save. |
| `Loading`, `FullPageLoading`, `EmptyState`, `ErrorState`, `ComingSoon` | The states. Empty and error are dashed boxes with one sentence and, if useful, one action (Retry, Create). Never a blank area. |
| `Pager` | Hidden when there is one page; "Página 2 de 5" between ghost buttons. |
| `Icon` | Feather-style 24-grid strokes, `currentColor`, 2px. Sizes 16 (in buttons), 18 (tabs, modal close). An unknown name draws nothing; any icon the API names must exist here. |

### What colour an action is

The colour says what the button does, the same everywhere (`ACTIONS` in `kit.tsx`):

| Action | Hue | For |
|---|---|---|
| `confirm` | green | emit, complete, confirm, enable |
| `danger` | red | void, remove, cancel, stop |
| `edit` | blue | edit |
| `open` | violet | view, open, download, go to a related page |
| `setup` | indigo | add to something, act as someone |

### What colour a status is

One map (`toneFor` in `kit.tsx`) feeds badges, row tints and legends, so they never disagree. A value not in the map
is neutral, which reads as "no status". Pass a status to `Badge` and `Row`, never a tone name (`value="info"` is not a
status and shows grey).

| Status | Tone | Meaning |
|---|---|---|
| `draft` | warning | a document not in the books yet |
| `emitted` | info | waiting for its money or its answer |
| `partially_paid` | accent | on its way |
| `paid`, `accepted` | success | done |
| `rejected`, `expired`, `voided` | neutral | over |
| `prospect` | warning | an invited user who has not accepted yet |
| `owner` | info | the owner badge on the users list |
| `own_account` | info | an account the company added to the PUC |
| `cancelled`, `inactive` | neutral | a deactivated user; an inactive tercero, product, tax, payment method or account |

A new status goes in `TONES` (and `tones.test.ts`), never as a class on a page.

## Interaction and accessibility

- **Focus:** a 2px `--color-primary` outline, 2px offset, on every button, tab, link and input (`:focus-visible`).
- **Touch targets:** every control reaches 44px — inputs are 44px tall, and buttons smaller than that get an invisible
  44px hit area (`::before`).
- **Motion:** 0.15s colour transitions, a 0.2s drawer slide, the spinner. All honour `prefers-reduced-motion`.
- **Disabled:** 55% opacity and `not-allowed`; a busy button says "Procesando…" instead of its label.
- **Hover-only content** (tooltips) is hidden on touch devices and also shown on keyboard focus.
- Long names and identifiers wrap (`overflow-wrap: anywhere`) instead of pushing the layout wider than the screen.

## Content

- **Spanish (Colombia) only, through `t()`.** Copy lives in `shared/i18n/locales/es/<namespace>.json`; shared words
  (Acciones, Cancelar, Guardar, Buscar, Cargando…) are in `common.json`. No English and no raw keys on screen.
- **Money:** `$ 1.190.000,00` via `formatMoney` (`shared/lib/format.ts`), from the API's decimal string, display only.
- **Dates:** `DD/MM/YYYY` via `formatDate`, on Bogotá's calendar. **NIT:** `900123456-8` via `formatNit`.
- **Voice:** plain, short, second person (*tú*). Buttons are verbs (**Emitir**, **Anular**, **Guardar**); empty states
  say what is missing and offer the one action that fixes it; errors say what happened and what to do.

## Outside the app

- **PDFs** (`backend/templates/pdf/`, dompdf): DejaVu Sans 9pt on white, ink `#1f2933`, greys for labels. They are
  printed documents, not themed, and keep their own hex values.
- **E-mails** (`backend/templates/email*/`): inline styles, Arial, the same restraint; light only.
- **Public pages** (sign-in, sign-up, password reset) use the kit's `.auth-page` / `.auth-card`: one 400px card centred
  on the page background.

## Adding a screen

1. Compose it from `@/shared/ui`; reach for page CSS only for layout (grids, widths), scoped under `.admin`, using
   tokens and no hex values. Something two pages need goes in the kit instead.
2. Lists: `PageHeader` → `FilterBar` → `RowLegend` (if tinted) → `DataTable` with `Row` and `Actions` → `Pager`.
3. Create and edit: `FormModal` for short forms; a page with `Card`s for documents.
4. Every list has its loading, empty and error state.
5. Check it at laptop and tablet widths, light and dark, with the keyboard only.
