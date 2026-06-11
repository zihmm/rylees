# Developer Console — Design Specification

Status: v1 — Visual/design companion to `./SPEC.md`.

This document specifies the **visual design and interaction** of the Developer Console app (`console.rylees.ai`). It complements the functional implementation guide in [`./SPEC.md`](./SPEC.md) §8–§9 — that document defines *what data flows and behaviours* are required; this one defines *how it looks*. Where the two differ, the deviations are called out in §11 and take precedence for the visual layer.

> File placed in `src/frontend/spec/` (singular) alongside `SPEC.md` and `SPEC-RH.md`.

**Figma source:** file `d0AGhyS28LBVhJPFGg8ezO` ("Layout-Webapp-Developer-Console")

| Frame | Node | Purpose |
| ----- | ---- | ------- |
| Login | `6:628` | Public login |
| Register | `14:1476` | Public registration (multi-section) |
| Public 404 Error | `6:766` | Pre-auth 404 |
| Projects overview | `4:2` | The 3-column app shell **+ overview card-list prototype** |
| Projects Edit | `6:458` | The **form prototype** (incl. validation error) + release-notes sidebar |
| Projects Edit: More Fields | `14:1350` | Same form with LLM temperature field |
| App 404 Error | `6:1055` | In-app 404 |
| App 500 Error | `7:1227` | In-app 500 |

---

## 1. Confirmed design decisions

| Topic | Decision |
| ----- | -------- |
| Icons | Exported from Figma as local **SVG** into `src/assets/icons/`; illustrations (404/500/empty-state) exported as **SVG/PNG** into `src/assets/illustrations/`. |
| Typeface | **Noto Sans** (single UI family) + a **monospace** for the API token. The Figma mixes Noto Sans/Roboto/Roboto Mono/Avenir Next; we consolidate to Noto Sans for consistency with `SPEC-RH.md`. |
| Brand accent | **`#ffc00e`** — the canonical brand gold (from this design + the logo). `SPEC-RH.md` is aligned to the same value. |
| Overviews | **Card lists** (per Figma), not tables — supersedes `SPEC.md` AC-FE-08. Applies to all overviews (Organisations, Projects). |
| Nav taxonomy | UI label **"Organisations" = the spec's Customers**. A top-level **"Projects"** overview lists all projects, backed by a **new global `/projects` aggregate endpoint** (backend addition, outside frontend scope). |
| Project "Language" field | Treated as a **real new project field** (requires an API/schema addition, outside frontend scope). UI specified in §8. |
| Registration | Add the **password (+ confirm)** field and the missing org fields (`postcode`, `website`, `email`) to match the API, styled like the Figma. |

---

## 2. Design tokens

No Figma variables are defined — values extracted from the frames. Add to `tailwind.config.js` `theme.extend` and share with the Release History app.

| Token | Value | Usage |
| ----- | ----- | ----- |
| Accent (gold) | `#ffc00e` | Active nav, primary buttons, links, active status |
| Content background | `#ffffff` | Center content area |
| Panel background | `#f9fafb` | Left nav + right sidebar |
| Divider / border | `#e9e9ea` | Input borders, panel dividers |
| Primary text | `#141414` / `#000000` | Nav items, values, headings |
| Field label | `#2e2e2e` | Form labels (13px) |
| Helper text | `#747474` | Field help / hints (11px) |
| Muted label | `#a5a5a5` / `#777777` | Section headers ("Navigation", "Current Projects") |
| Title inactive | `#c1c1c1` | Breadcrumb parent segment |
| Meta / date | `#adadad` | Relative dates (13px) |
| **Error** | `#df5e70` | Invalid field label, border, and message |
| Version pill | bg `#f4f0eb`, text `#5f5f5f` | `v1.3.2` badge |
| BETA pill | bg `#fcf1c3`, text `#5f5f5f` | status badge (decorative) |
| Token field | bg `rgba(233,233,234,0.42)` | read-only token, mono font |
| Cancel button | bg `#e9e9ea`, text white | secondary action |
| Input | h `44px`, radius `4px`, border `#e9e9ea` | text inputs / selects; textarea h `86px` |
| Sidebar heading | `#7a7a7a` | "Release Notes" panel title |

Layout widths: left nav **274px**, right sidebar **379px**, center fluid. Desktop-first (frames are 1400×800).

```javascript
theme: {
  extend: {
    colors: {
      accent: '#ffc00e',
      panel: '#f9fafb',
      'field-border': '#e9e9ea',
      ink: '#141414',
      'field-label': '#2e2e2e',
      helper: '#747474',
      muted: '#a5a5a5',
      meta: '#adadad',
      danger: '#df5e70',
    },
    borderRadius: { field: '4px', pill: '25px' },
    fontFamily: { sans: ['Noto Sans', 'system-ui', 'sans-serif'], mono: ['ui-monospace', 'monospace'] },
  },
}
```

---

## 3. Typography

Single family **Noto Sans** (weights 400/500/600/700), monospace for the token.

| Role | Weight | Size | Color |
| ---- | ------ | ---- | ----- |
| Breadcrumb title (active / parent) | 500 | 17px | `#000` / `#c1c1c1` |
| Nav item | 500 | 14px | `#141414` (active `#ffc00e`) |
| Section header (nav groups) | 500 | 14px | `#a5a5a5` / `#777` |
| Form label | 400 | 13px | `#2e2e2e` (error `#df5e70`) |
| Input value | 400 | 14px | `#000` |
| Helper / hint | 400 | 11px | `#747474` |
| Error message | 400 | 11px | `#df5e70` |
| Pill (version/BETA) | 800 | 11px | `#5f5f5f` |
| Date / meta | 400 | 13px | `#adadad` |
| Token value | mono | 14px | `#000` |

---

## 4. Icon & illustration inventory

Export as individual SVG into `src/assets/icons/` (inherit `currentColor`):

| Icon | Use | Node (ref) |
| ---- | --- | ---------- |
| `logo-bolt` | Rylees mark (nav + auth) | `6:525` |
| `dashboard` | Nav: Dashboard (grid) | `6:472` |
| `organisations` | Nav: Organisations (briefcase) | `6:477` |
| `projects` | Nav: Projects (folder) | `6:471` |
| `user` | Footer user row | `6:464` |
| `kebab` | menu-dots-vertical (user menu trigger) | `6:495` |
| `key` | User menu: API Key | `6:178` |
| `gear` | User menu: Settings | `6:176` |
| `copy` | Copy token to clipboard | `6:1190` |
| `chevron-down` | Select dropdowns | `6:540` / `6:553` |
| `chevron-right` | Breadcrumb + overview card link | `6:491` / `6:112` |
| `check` | Save button | `6:544` |
| `plus` | New Project / New … button | `6:56` |
| `briefcase-sm` | Org marker next to names (overview/sidebar) | `imgGroup4` |

Illustrations → `src/assets/illustrations/`:

| Illustration | Use | Node |
| ------------ | --- | ---- |
| `error-404` (monkey + keyboard) | Public & app 404 | `6:785` / `6:1144` |
| `error-500` (protest monkey) | App 500 | `7:1298` |
| `empty-projects` (monkey) | Overview right-sidebar empty state | `6:66` |

**Not icons** (build with CSS): timeline/status dots, dividers, pills, input chrome.

---

## 5. App shell (3-column layout)

Used by every authenticated view (`ConsoleLayout` component).

**Left nav (274px, `#f9fafb`):**
- Rylees logo top-left.
- Section "Navigation" (`#a5a5a5`) → items **Dashboard / Organisations / Projects**, each with icon; active item text + icon in `#ffc00e`.
- Section "Current Projects" (`#777`) → quick list of projects, each prefixed by a small **status dot** (green/red/grey — decorative; map to most-recent activity or omit).
- Footer: hairline divider, **user row** = person icon + first name (`Marc`) + **kebab** button. Kebab opens a popover card with **API Key**, **Settings**, **Logout** (key/gear icons; logout no icon, divider above it).

**Center content (fluid, white):**
- Top: breadcrumb-style title — parent segment in `#c1c1c1` + `chevron-right` + current in black (e.g. `Projects › Vertex E-Commerce Engine`). Bottom hairline under the header.
- Body: the view's content (overview card list, or form).

**Right sidebar (379px, `#f9fafb`):** contextual.
- On an overview with no selection → **empty state**: `empty-projects` illustration + caption + primary **New …** button.
- On a project detail/edit → **"Release Notes"** panel (`#7a7a7a` heading): a compact list of release notes, each with a colored dot, body excerpt, relative date, and version pill (+ optional BETA pill).

---

## 6. Auth pages (public)

Centered white card (~479px) on a light page, Rylees logo at top, gold primary button.

**Login (`6:628`):** underline-style **E-Mail** and **Password** inputs, full-width **Login** button (`#ffc00e`), footer links "Create account" (→ `/register`) `or` "forgot password?". *Note:* there is no forgot-password backend — wire only "Create account"; render "forgot password?" inert or omit (see §11).

**Register (`14:1476`):** "Back to login" link (chevron-left) + logo + heading "Register Account". Two sections with muted headers:
- **ACCOUNT** — Firstname, Lastname, E-Email, **Password**, **Confirm password** *(password fields added vs. Figma — required by API, min 12 chars)*.
- **ORGANISATION** — Name, Street, City, **Postcode**, **Website**, **Email** *(extra org fields added to match API; optional)*.
- Full-width **Register** button. Inline field errors render in `#df5e70` below each input (see §9).

**Public 404 (`6:766`):** white card, `error-404` illustration, text "404 - We can't find your page", gold link "← Go back to login".

---

## 7. Overview pattern (card list)

Prototype: "Projects overview" (`4:2`). Applies to **Organisations** and **Projects** overviews. Supersedes AC-FE-08 tables.

- Vertical list of **cards** separated by hairlines. Each card:
  - Small **briefcase** icon + **owning org/customer name** (e.g. `Acme AG`).
  - **Title** (project/org name, bold).
  - **Description** excerpt (truncated, grey).
  - **Version pill** (`v1.3.2`) and optional **BETA pill** (decorative).
  - Right side: **"Updated 3 months ago"** (relative) + **chevron-right** linking to detail.
- Header: section title + a primary **New …** button lives in the right sidebar (overview empty state) per §5.
- Version = derived from the project's latest release note; relative date via the shared date helper.

---

## 8. Form pattern (create / edit)

Prototype: "Projects Edit" (`6:458` / `14:1350`). Applies to all create/edit forms.

**Field row:** left-column **label** (`#2e2e2e`, 13px) with optional **helper text** (`#747474`, 11px) below it; right-column control. Rows separated by hairlines.

**Controls:**
- **Text input / select:** h 44px, radius 4px, border `#e9e9ea`; selects show a `chevron-down`.
- **Textarea:** h ~86px.
- **Read-only token:** filled field `rgba(233,233,234,0.42)`, monospace value, trailing **copy** icon; helper "Use this token in your CLI project".

**Project edit — real fields** (placeholders "Single Textfield"/"Comment" from the Figma are illustrative only): **Name**, **Token** (read-only + copy), **Language** *(new project field — see §1/§11)*, **LLM tonality** (select), **LLM temperature** (select, "More Fields" frame), **Description**. `token`/`key` are never editable.

**Footer actions** (bottom-right, above a hairline): **Cancel** (`#e9e9ea`, white text) + **Save** (`#ffc00e`, white text, `check` icon).

**Right sidebar:** the project's **Release Notes** timeline (read context while editing).

**Detail vs. edit:** `ProjectDetailView` renders the same 3-column layout with **read-only** field values + token + release-notes sidebar; `ProjectEditView` swaps the fields to inputs with Save/Cancel. (Reconciles the Figma's merged screen with the spec's separate routes.)

---

## 9. Validation error styling

From the "Single Textfield" example (`6:586`/`6:587`/`6:591`):
- Invalid field **label** turns `#df5e70`.
- Invalid input **border** turns `#df5e70`.
- A **message** in `#df5e70` (11px) renders directly below the input (e.g. "Field is required").
- Applies to all inline API (422) and client-side validation errors across every form.

---

## 10. Error pages (in-app)

Rendered inside the app shell (nav + sidebar visible), illustration centered in content:
- **404 (`6:1055`):** `error-404` illustration, "404 - We can't find your page", gold link "← Go back to dashboard".
- **500 (`7:1227`):** `error-500` illustration, "Ooh ooh. We have a Server Error. Happens to the best of us."

---

## 11. Deviations from `SPEC.md`

These supersede the corresponding functional-spec lines for the visual layer; items marked **(backend)** also need API work outside the frontend scope:

1. **Overviews are card lists, not tables** — supersedes AC-FE-08's table requirement.
2. **"Customers" is labelled "Organisations"** throughout the console UI (same entity, routes unchanged).
3. **Global "Projects" overview** backed by a new aggregate **`/projects` endpoint (backend)**.
4. **New project `language` field (backend)** — added to the project edit form.
5. **Registration adds Password (+confirm) and org `postcode`/`website`/`email`** to match the API (the Figma omitted them).
6. **No forgot-password flow** — link rendered inert/omitted (no backend).
7. **Project detail & edit share one 3-column layout** (read-only vs. input variants).
8. **Status dots & BETA pill are decorative** (no API backing); version + relative dates are derived.

---

## 12. Component breakdown (`src/apps/console/`)

| Component | Responsibility |
| --------- | -------------- |
| `components/ConsoleLayout.vue` | 3-column shell: `<SideNav>`, `<RouterView>` (content), `<ContextSidebar>` slot. |
| `components/SideNav.vue` | Nav groups, Current Projects list, user row + menu popover. |
| `components/UserMenu.vue` | API Key / Settings / Logout popover. |
| `components/PageHeader.vue` | Breadcrumb-style title. |
| `components/OverviewList.vue` + `OverviewCard.vue` | Card-list overview (organisations + projects). |
| `components/FormRow.vue`, `TextField.vue`, `SelectField.vue`, `TextArea.vue`, `TokenField.vue` | Form primitives with the §9 error states. |
| `components/Pill.vue` | Version / BETA / status pills. |
| `components/ReleaseNotesPanel.vue` | Right-sidebar release-notes timeline. |
| `components/EmptyState.vue` | Illustration + caption + action. |
| `components/AuthCard.vue` | Centered card shell for Login/Register/404. |
| `components/icons/*`, `illustrations/*` | Exported SVG/PNG assets. |

---

## 13. Build / verify notes

- Add Noto Sans + a date library; extend `tailwind.config.js` per §2 (shared with Release History).
- Visual-check each view against its Figma frame (table in the header).
- Functional acceptance criteria still apply except the §11 deviations.
- **Backend dependencies to track:** global `/projects` endpoint, project `language` field. The frontend can stub these until available.
