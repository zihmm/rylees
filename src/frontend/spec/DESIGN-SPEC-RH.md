# Release History — Design Specification

Status: v1 — Visual/design companion to `./SPEC.md`.

This document specifies the **visual design and interaction** of the public Release History app (`{customer-slug}.rylees.ai/{project-key}`). It complements the functional implementation guide in [`./SPEC.md`](./SPEC.md) §10 — that document defines *what data flows and behaviours* are required; this one defines *how it looks and animates*. Where the two differ, the deviations are called out explicitly in §8.

**Figma source:** file `Q3i5cgFykkJ7UiZMSXcvZA` ("Layout-Release-History")
- Frame **"Release Notes"** — node `4:2` (start page, timeline)
- Frame **"Release History"** — node `4:70` (flip target, accordion)

---

## 1. Confirmed design decisions

| Topic | Decision |
| ----- | -------- |
| Icons | Exported from Figma as local **SVG** files into `src/assets/icons/`, rendered as inline Vue SVG components. No icon-library dependency, no remote URLs. |
| Typeface | **Noto Sans** (Google Fonts), weights 400 / 600 / 700. |
| Dates | Rendered as **relative time** (e.g. "Vor 3 Tagen"), derived from `publishedAt` via a date library (e.g. `dayjs` + `relativeTime`), localized per active language. |
| Author | **Dropped** from the public timeline — the public API returns no author field. |
| View transition | **CSS 3D flip** (`rotateY`) via Vue `<Transition>`, no animation library. |
| Language switcher (DE/EN/FR) | **Deferred** to a later feature. The translate logic stays in place but no switcher UI control is built in this iteration. |

---

## 2. Design tokens

No Figma variables are defined in the source file — all values below were extracted from the frames and MUST be added to `tailwind.config.js` under `theme.extend` (colors, borderRadius, boxShadow, fontFamily) so they are reusable across both apps.

| Token | Value | Usage |
| ----- | ----- | ----- |
| Page background | `#f5f4f7` | App canvas behind the card |
| Surface / card | `#ffffff` | The release-history card |
| Card border | `#dcdcdc` | 1px card outline |
| Card radius | `10px` | Card and header icon-button corners |
| Card shadow | `0 0 14px -5px rgba(0,0,0,0.25)` | Card elevation |
| Title text | `#2a2a2a` | Project name / primary headings |
| Body text | `#000000` | Release note body |
| Muted text | `#686868` | Secondary meta (major "last update") |
| Faint text | `#adadad` | Sub-version dates |
| Inactive label | `#bebebe` | "History" header label on Face B |
| Sub-heading | `#6b6b6b` | Sub-version titles ("Version 3.5") |
| Accent (gold) | brand yellow (matches the logo lightning bolt, `~#FFC107`) | Current-version label, active timeline ring/dot |
| Ghost button bg | `rgba(217,217,217,0.34)` | Header clock / back icon button |

Suggested Tailwind mapping (illustrative — final names at implementer's discretion):

```javascript
theme: {
  extend: {
    colors: {
      page: '#f5f4f7',
      card: '#ffffff',
      'card-border': '#dcdcdc',
      ink: '#2a2a2a',
      muted: '#686868',
      faint: '#adadad',
      'label-inactive': '#bebebe',
      subhead: '#6b6b6b',
      accent: '#FFC107',
    },
    borderRadius: { card: '10px' },
    boxShadow: { card: '0 0 14px -5px rgba(0,0,0,0.25)' },
    fontFamily: { sans: ['Noto Sans', 'system-ui', 'sans-serif'] },
  },
}
```

---

## 3. Typography

Single family: **Noto Sans**, loaded via `@fontsource/noto-sans` or a Google Fonts `<link>`.

| Role | Weight | Size / line-height | Color |
| ---- | ------ | ------------------ | ----- |
| Project name (header) | 600 | 20px / 22px, uppercase | `#2a2a2a` |
| Current version label (Face A header) | 600 | 15px | accent gold |
| "History" label (Face B header) | 600 | 15px | `#bebebe` |
| Major version title ("Version 3.x") | 700 | 15px / 22px | `#000000` |
| Sub-version title ("Version 3.5") | 600 | 15px / 22px | `#6b6b6b` |
| Version pill text ("v1.3.2") | 700 | 13px | `#2a2a2a` |
| Release body | 400 | 14–15px / 22px | `#000000` |
| Meta / dates | 400 | 13px / 22px | `#686868` / `#adadad` |

---

## 4. Icon inventory

Export each as an individual SVG into `src/frontend/src/assets/icons/` and wrap as a small inline-SVG Vue component (`<IconClock/>`, etc.). All are line/monochrome icons that should inherit `currentColor`.

| Icon | Where used | Figma node |
| ---- | ---------- | ---------- |
| `clock` | Face A header button (→ flip to History) | `4:61` |
| `arrow-left` | Face B header button (→ flip back to Notes) | `4:146` (Vector in group `4:185`) |
| `bookmark` | Major group marker (Face B) — tinted per major | `4:99` / `4:138` / `4:150` |
| `document` | Sub-version marker (Face B) | `4:111` / `4:120` / `4:126` |
| `chevron` | Accordion expand (down) / collapse (up) | `4:158` / `4:187` / `4:160` |

**Not icons** — build with CSS/SVG, do not export: timeline ring & filled dot, vertical timeline line, horizontal sub-version connector stubs, version pills, the header vertical divider.

---

## 5. Layout — shared shell

- Centered **card**, max-width ~750px, on the `#f5f4f7` page (vertically/horizontally centered with generous margin).
- Card: white, `border: 1px solid #dcdcdc`, `border-radius: 10px`, `box-shadow: 0 0 14px -5px rgba(0,0,0,0.25)`.
- **Header row** (shared by both faces), with a bottom hairline divider:
  - Left: **project name** (uppercase) · thin vertical divider · **state label**.
  - Right: square **ghost icon-button** (~40×37, bg `rgba(217,217,217,0.34)`, radius 10).
- The project name comes from the `getReleaseHistory` response `project.name`.

---

## 6. Face A — "Release Notes" (timeline)

Header: state label = **current version** (the highest version among `items`, computed client-side), in accent gold. Button = **clock** icon → flips to Face B.

Body — a vertical timeline rendered from `items[]` (API already returns newest first):

- A continuous **vertical line** runs down the left of the content column.
- Each release renders a **timeline node**:
  - **Marker:** the newest entry uses a hollow **gold ring** (~21px); all older entries use a filled **gold dot**.
  - **Version pill:** light-grey rounded-full badge with the version (e.g. `v1.3.2`), text `#2a2a2a`, weight 700.
  - **Body:** full release text via **text interpolation only** — never `v-html` (see SPEC.md AC-FE-14). Preserve paragraph breaks.
  - **Meta line:** relative date only (e.g. "Vor 3 Tagen"), color `#adadad`. No author.

---

## 7. Face B — "Release History" (accordion by major)

Header: state label = **"History"** in `#bebebe`. Button = **arrow-left** → flips back to Face A.

Body — `items[]` grouped **client-side by major version** (`Version 3.x`, `Version 2.x`, …), newest major first:

- Each major is an **accordion row**:
  - Colored **bookmark** icon in a soft circular tint that cycles per major (observed: 3.x purple, 2.x gold, 1.x pink/red).
  - **Major label** (e.g. "Version 3.x", weight 700) with a relative **"last update"** subtitle (`#686868`), derived from the newest sub-version's `publishedAt`.
  - **Chevron** on the right: pointing **down** when collapsed, **up** when expanded.
- **Expanded** content reveals the major's sub-versions, newest first. Each sub-version row:
  - **Document** icon + a short horizontal **connector stub** off the group's vertical line.
  - Sub-version title (e.g. "Version 3.5", `#6b6b6b`, weight 600).
  - `Last Update: DD. MMMM YYYY` (absolute, localized), color `#adadad`. *(Within the accordion the Figma uses absolute dates; the Face A timeline uses relative — see §8.)*
- Clicking a major row's chevron / row toggles that accordion open/closed (independent per major).

---

## 8. Deviations from the functional spec (`SPEC.md`)

These are intentional and supersede the corresponding lines in `SPEC.md` for the visual layer:

1. **AC-FE-12 (date format):** the timeline (Face A) shows **relative** dates instead of `DD. MMMM YYYY`. The accordion sub-versions (Face B) retain the absolute `DD. MMMM YYYY` format.
2. **AC-FE-13 (language switcher):** the DE/EN/FR switcher is **deferred** — not rendered in this iteration. The `switchLanguage` / `translateReleaseHistory` plumbing remains for the future feature.
3. **Author:** `SPEC.md` shows no author and the public API returns none — the Figma's "Marc Zimmerli" line is **dropped** (date only).
4. **Two-face flip:** the spec describes a single timeline view; this design adds a second **accordion** face and a flip transition between them (§9). Both faces consume the same `getReleaseHistory` data — no new API calls.

---

## 9. Flip interaction

- The card has two faces (Face A / Face B). A reactive `face` ref toggles between them.
- The clock button flips A→B; the back-arrow flips B→A.
- Implemented as a **CSS 3D flip** (`transform: rotateY(180deg)`, `transform-style: preserve-3d`, `backface-visibility: hidden`) driven by a Vue `<Transition>` / class toggle — no library.
- Both faces share the header shell; only the state label, the header button icon, and the body content differ.

---

## 10. Component breakdown (`src/apps/history/`)

| Component / file | Responsibility |
| ---------------- | -------------- |
| `views/ReleaseHistoryView.vue` | Data load (existing SPEC.md logic), flip `face` state, holds both faces. |
| `components/HistoryCard.vue` | Shared card + header shell (slots for label and header button). |
| `components/ReleaseTimeline.vue`, `TimelineEntry.vue` | Face A timeline + entry. |
| `components/VersionAccordion.vue`, `MajorGroup.vue`, `SubVersionRow.vue` | Face B accordion, major group, sub-version row. |
| `components/icons/*` | Exported SVG icon components (clock, arrow-left, bookmark, document, chevron). |
| `composables/useVersionGrouping.js` | Group `items` by major; compute current/highest version. |
| `composables/useRelativeDate.js` | Localized relative-time + absolute-date helpers. |

---

## 11. Build / verify notes

- Add Noto Sans + the date library to `package.json`; extend `tailwind.config.js` per §2.
- Existing acceptance criteria still apply except the §8 deviations: AC-FE-11 (data loading), AC-FE-14 (no `v-html`).
- Visual check each face against the Figma frames (`4:2`, `4:70`); verify the flip animation and the per-major accordion toggle.
