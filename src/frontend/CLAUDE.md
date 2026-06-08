# Frontend Sub-project

## Scope
This directory contains the Rylees frontend — two Vue 3 SPAs (Developer Console + Public Release History).

Work ONLY in `src/frontend/` when changes belong to the frontend.

## Tech Stack
- **Framework**: Vue 3 (Composition API, `<script setup>`)
- **Build tool**: Vite
- **Router**: Vue Router 4
- **State**: Pinia
- **HTTP client**: Axios
- **Testing**: JEST, Vue 3 testing utils
- **CSS**: Tailwind CSS v3
- **Icons**: Provided in Figma design

## Project Layout

```
src/frontend/
├── src/
│   ├── apps/
│   │   ├── console/          # Developer Console (console.rylees.ai)
│   │   │   ├── main.js
│   │   │   ├── App.vue
│   │   │   ├── router/
│   │   │   ├── stores/
│   │   │   ├── views/
│   │   │   └── components/
│   │   └── history/          # Public Release History ({slug}.rylees.ai/{key})
│   │       ├── main.js
│   │       ├── App.vue
│   │       └── views/
│   └── shared/               # Shared composables, types, API client
│       ├── api.js
│       └── types.js
├── console.html              # Vite entry for Developer Console
├── history.html              # Vite entry for Release History
├── vite.config.js
└── tailwind.config.js
```

Two Vite entry points: `console` → `console.html`, `history` → `history.html`.

## Key Rules
- Auth state stored in Pinia `useAuthStore`, persisted in `localStorage`
- On mount: restore from localStorage, validate via `GET /users/me`
- Axios MUST inject `Authorization: Bearer <token>` when token is stored
- `v-html` MUST NOT be used with any user-controlled or API free-text content
- Default language for Release History: DE
- Language switcher triggers translate endpoint; shows loading skeleton per entry
- Switching back to DE restores originals without a new API call

## Developer Console Routes
`/login`, `/register`, `/activate` — public
All other routes — require auth, redirect unauthenticated users to `/login`

## Release History Subdomain Resolution
1. `customerSlug` from `window.location.hostname` subdomain
2. `projectKey` from first path segment of `window.location.pathname`

## Spec & Docs
- Full specification: `/SPEC.md` (section 6 — Frontend Component)
- Architecture docs: `/docs/architecture/`
- Design docs: `/docs/design/`
