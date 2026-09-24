# BookPoint Design System

One visual language for the booking wizard, the manage page, the customer portal and the admin
app. Everything here is original to BookPoint: tokens, components, icons and illustrations.

Source of truth: `src/ui/` (tokens in `src/ui/tokens.css`, brand maths in `src/ui/theme/brand.js`,
components in `src/ui/components/`, icons in `src/ui/icons/`).

## 1. Principles

1. **Calm and clear.** White space, one accent colour, few borders. The customer should always know
   what to do next.
2. **Business-neutral.** Works for a barber, a clinic, a tutor or a yoga studio. No niche imagery.
3. **Fast by default.** System fonts, CSS-only animation, no web fonts, no CDNs.
4. **Accessible.** WCAG 2.2 AA contrast, visible focus, full keyboard use, correct ARIA, reduced
   motion respected, 44 × 44 px touch targets on the customer side.
5. **Safe inside any theme.** Front-end CSS lives under `.pbk-root` and resets what themes
   commonly override. All custom properties are prefixed `--pbk-`.

## 2. Colour

### Brand scale

The business picks one colour (**Booking form → Appearance**, default `#4f46e5`). `brandTokens()`
builds a ten-step scale from it by mixing in OKLab space:

| Token | Use |
|-------|-----|
| `--pbk-brand-50` / `100` | Selected backgrounds, subtle highlights |
| `--pbk-brand-200` / `300` | Borders of selected items, focus halo |
| `--pbk-brand-400` / `500` | Icons, progress |
| `--pbk-brand-600` | **The chosen colour.** Primary buttons, active states |
| `--pbk-brand-700` | Hover/pressed primary |
| `--pbk-brand-800` / `900` | Rare, dark accents |
| `--pbk-brand-contrast` | Text/icon colour on `brand-600` (white or ink, whichever reaches 4.5:1) |
| `--pbk-brand-text` | Brand colour darkened until it reaches 4.5:1 on white (links, selected labels) |

The scale is recalculated live in the admin preview and passed to the front end as inline custom
properties on the widget root, so a changed colour never needs a rebuild.

### Neutrals (cool grey)

| Token | Light | Dark (admin, dark-ready front) |
|-------|-------|--------------------------------|
| `--pbk-bg` | `#f5f6f8` | `#0f1115` |
| `--pbk-surface` | `#ffffff` | `#171a21` |
| `--pbk-surface-2` | `#f9fafb` | `#1d212a` |
| `--pbk-surface-3` | `#eff1f4` | `#262b36` |
| `--pbk-border` | `#e2e5ea` | `#2e3441` |
| `--pbk-border-strong` | `#cbd0d8` | `#3c4352` |
| `--pbk-text` | `#171a21` | `#eef0f4` |
| `--pbk-text-muted` | `#4f5767` | `#b3b9c5` |
| `--pbk-text-subtle` | `#667085` | `#8b93a3` |

Contrast on `--pbk-surface`: text 17.5:1, muted 7.3:1, subtle 4.9:1; dark subtle on dark surface 5.7:1 (all AA).

### Semantic

| Tone | Text | Background | Border |
|------|------|------------|--------|
| success | `#12703f` | `#e9f7ef` | `#b6e3c8` |
| warning | `#8f5200` | `#fff5e5` | `#f5d49a` |
| danger | `#b42318` | `#fdeeec` | `#f5c2bd` |
| info | `#1d5fa8` | `#eaf2fc` | `#bcd5f3` |

Booking statuses: pending → warning, confirmed → success, completed → info, cancelled → neutral
(strike-through time), pending payment → brand, failed payment → danger.

## 3. Typography

System stack: `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial,
"Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji"`. Numbers use `tabular-nums` in
tables, prices and times.

| Token | Size / line height | Use |
|-------|--------------------|-----|
| `--pbk-text-xs` | 12 / 16 | Captions, badges |
| `--pbk-text-sm` | 13 / 18 | Secondary text, table cells (admin) |
| `--pbk-text-md` | 14 / 20 | Admin body |
| `--pbk-text-base` | 16 / 24 | Customer body, inputs (prevents iOS zoom) |
| `--pbk-text-lg` | 18 / 26 | Card titles |
| `--pbk-text-xl` | 20 / 28 | Section titles |
| `--pbk-text-2xl` | 24 / 32 | Page titles |
| `--pbk-text-3xl` | 30 / 38 | Wizard success, KPI values |
| `--pbk-text-4xl` | 36 / 44 | Rare hero numbers |

Weights: 400 regular, 500 medium (labels), 600 semibold (titles, buttons), 700 bold (KPIs).

## 4. Space, radius, elevation, motion

- **Spacing** on a 4 px grid: `--pbk-space-1` 4, `2` 8, `3` 12, `4` 16, `5` 20, `6` 24, `8` 32,
  `10` 40, `12` 48, `16` 64.
- **Radius**: `--pbk-radius-sm` 6 (chips, inputs inside tables), `--pbk-radius-md` 10 (inputs,
  buttons, cards inside cards), `--pbk-radius-lg` 16 (cards, modals), `--pbk-radius-pill` 999.
- **Shadows** (soft, low-contrast, two layers each):
  `--pbk-shadow-sm` (cards at rest), `--pbk-shadow-md` (dropdowns, hover), `--pbk-shadow-lg`
  (modals, drawers).
- **Motion**: `--pbk-duration-fast` 150 ms (hover, press), `--pbk-duration` 200 ms (open/close),
  `--pbk-duration-slow` 250 ms (sheets, step transitions); easing `--pbk-ease`
  `cubic-bezier(.2,.8,.2,1)`. With `prefers-reduced-motion: reduce` every duration becomes 0.01 ms
  and slide effects become fades.
- **Layers**: dropdown 20, sticky 30, drawer 100000, modal 100010, toast 100020 (above most
  theme headers and the WP admin bar).

## 5. Layout and breakpoints

Mobile first. Tested widths: 320, 375, 414, 768, 1024, 1280, 1440. Breakpoints: `sm` 600 px,
`md` 782 px (matches WP admin), `lg` 1024 px, `xl` 1280 px. No horizontal scrolling at 320 px:
tables become stacked cards below `md`, long words wrap (`overflow-wrap: anywhere`).

Touch targets: every interactive element on the customer side is at least 44 × 44 px. Admin
controls are 36 px tall on desktop and grow to 44 px on coarse pointers.

## 6. Components

| Component | Notes |
|-----------|-------|
| `Button` | `primary`, `secondary`, `ghost`, `danger`, `link`; sizes `sm` `md` `lg`; `loading` keeps width, shows a spinner, sets `aria-busy`; optional icon left/right; `block`; renders `<a>` when `href` is set. |
| `IconButton` | Icon-only, required `label` (used for `aria-label` and tooltip). |
| `Field` | Label, required marker, help text, error text (`role="alert"`), wires `aria-describedby`/`aria-invalid`. |
| `Input`, `Textarea`, `Select` | Native elements, 16 px on the customer side, prefix/suffix slots, invalid state. |
| `Checkbox`, `Toggle` | Native checkbox; toggle is `role="switch"` with `aria-checked`. |
| `RadioCards` | Card-style single choice on native radios (arrow keys work natively); media, description and aside (price) slots. `ChoiceCard` is the multi-select sibling. |
| `Calendar` / `DatePicker` | Month grid, `role="grid"`, roving focus (arrows, Home/End, PageUp/PageDown), today marker, disabled days, availability dots, loading skeleton. `DateInput` opens it in a popover. |
| `TimeSlots` | Chips grouped into morning / afternoon / evening, `role="radiogroup"`, skeleton while loading, "few left" hint when capacity is low. |
| `Modal` | Portal, focus trap, `Esc`, `aria-modal`, restore focus, scroll lock (counted); becomes a full-screen sheet below 600 px; `onRequestClose` can be intercepted (confirm before closing). |
| `Drawer` | Side sheet from the inline end (RTL aware), same accessibility contract as `Modal`; bottom sheet on phones. |
| `Tabs` | `role="tablist"`, arrow keys, automatic activation; `underline` and `pills`. |
| `Stepper` | Horizontal (desktop) or compact "Step 2 of 5" (mobile); finished steps are buttons, future steps are inert; `aria-current="step"`. |
| `Card` | Header (title, description, actions), body, footer. |
| `Badge`, `StatusBadge` | Tones + optional dot; booking status mapping. |
| `Avatar` | Image or initials on a deterministic tint; sizes 24–56. |
| `Table` + `Pagination` | Sortable headers (`aria-sort`), row selection with bulk bar, skeleton rows, empty slot, stacked cards on small screens. |
| `Dropdown` / `Menu` | Button + `role="menu"`, arrow keys, type-ahead, `Esc`, flips to stay on screen. |
| `Tooltip` | Hover and focus, 300 ms delay, `aria-describedby`, never holds essential information. |
| `Toast` | `useToast()`, `aria-live="polite"` (errors `assertive`), 5 s auto-dismiss (paused on hover/focus), optional action. |
| `Skeleton` | Text lines, circle, block; shimmer disabled with reduced motion. |
| `EmptyState`, `ErrorState` | Illustration + title + text + action; error offers "Try again". |
| `ConfirmDialog` | `useConfirm()` returns a promise; danger tone for destructive actions. |
| `Spinner`, `Notice` | Loading indicator; inline message box with tones. |

## 7. Icons and illustrations

One set of 24 × 24 line icons drawn for BookPoint (`src/ui/icons/`): 1.75 px stroke, round caps
and joins, 2 px optical padding, `currentColor`. Icons are decorative (`aria-hidden`) unless used
alone, where the parent provides the label. Wizard step illustrations are simple geometric
compositions in the brand scale, drawn inline so they recolour with the brand.

## 8. Dark mode

- Admin: light, dark or system (toggle in the top bar, remembered per browser in
  `localStorage["pointlybooking_theme"]`), applied as `data-pbk-theme` on the app root.
- Front end: tokens are dark-ready. The booking form follows **Booking form → Appearance → Dark
  mode**: off (default), on, or "match the visitor's system".

## 9. Writing

Plain, friendly and short: "Choose a time", not "Select timeslot". Sentence case everywhere.
Buttons say what happens ("Confirm booking", "Save changes"). Errors say what to do next.
Times show the business time zone when it differs from the visitor's.
