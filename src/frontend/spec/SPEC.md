# Frontend Component — Implementation Specification

Status: v1 — Authoritative agent implementation guide.

This document is the authoritative guide for the **functional** implementation of the Frontend component — data flow, routing, state, API contracts, and behaviour. For the **visual design and interaction**, see the companion design specs:

- **Developer Console** — [`./DESIGN-SPEC-DC.md`](./DESIGN-SPEC-DC.md) (app shell, auth pages, overview/form patterns, error pages, tokens, icons).
- **Release History** — [`./DESIGN-SPEC-RH.md`](./DESIGN-SPEC-RH.md) (layout, timeline/accordion faces, flip transition, tokens, icons).

Where a design spec deviates from the acceptance criteria here, those deviations are listed in that spec's "Deviations" section (`DESIGN-SPEC-DC.md` §11 / `DESIGN-SPEC-RH.md` §8) and take precedence for the visual layer.

---

## 1. Overview

The Frontend is a single Vite project that builds two independent SPAs:

- **Developer Console** (`console.rylees.ai`) — authenticated app for managing customers, projects, API tokens, and account settings
- **Public Release History** (`{customer-slug}.rylees.ai/{project-key}`) — unauthenticated app displaying published release notes with language switching

**What this component does NOT do:** server-side rendering, backend logic, or any persistent storage beyond `localStorage`.

---

## 2. Technology Stack

| Concern | Choice |
| ------- | ------ |
| Framework | Vue 3.4+ (`<script setup>`, Composition API) |
| Build tool | Vite 5+ |
| Router | Vue Router 4 |
| State | Pinia 2 |
| HTTP client | Axios 1.6+ |
| CSS | Tailwind CSS v3 |
| Testing | Jest + `@vue/test-utils` |
| Node.js | 20 (LTS) |

### Complete `package.json`

```json
{
  "name": "rylees-frontend",
  "version": "0.1.0",
  "private": true,
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview",
    "test": "jest"
  },
  "dependencies": {
    "axios": "^1.6.0",
    "pinia": "^2.1.0",
    "vue": "^3.4.0",
    "vue-router": "^4.3.0"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0.0",
    "@vue/test-utils": "^2.4.0",
    "autoprefixer": "^10.4.0",
    "babel-jest": "^29.0.0",
    "jest": "^29.0.0",
    "jest-environment-jsdom": "^29.0.0",
    "postcss": "^8.4.0",
    "tailwindcss": "^3.4.0",
    "vite": "^5.2.0"
  }
}
```

---

## 3. Project Layout

```
src/frontend/
├── src/
│   ├── apps/
│   │   ├── console/
│   │   │   ├── main.js
│   │   │   ├── App.vue
│   │   │   ├── router/
│   │   │   │   └── index.js
│   │   │   ├── stores/
│   │   │   │   ├── auth.js
│   │   │   │   ├── customers.js
│   │   │   │   └── projects.js
│   │   │   ├── views/
│   │   │   │   ├── LoginView.vue
│   │   │   │   ├── RegisterView.vue
│   │   │   │   ├── ActivateView.vue
│   │   │   │   ├── DashboardView.vue
│   │   │   │   ├── CustomersView.vue
│   │   │   │   ├── CustomerCreateView.vue
│   │   │   │   ├── CustomerDetailView.vue
│   │   │   │   ├── CustomerEditView.vue
│   │   │   │   ├── ProjectCreateView.vue
│   │   │   │   ├── ProjectDetailView.vue
│   │   │   │   ├── ProjectEditView.vue
│   │   │   │   └── AccountView.vue
│   │   │   └── components/
│   │   └── history/
│   │       ├── main.js
│   │       ├── App.vue
│   │       ├── router/
│   │       │   └── index.js
│   │       └── views/
│   │           ├── ReleaseHistoryView.vue
│   │           └── NotFoundView.vue
│   └── shared/
│       └── api.js
├── console.html
├── history.html
├── vite.config.js
├── tailwind.config.js
└── postcss.config.js
```

---

## 4. Vite Configuration

### `vite.config.js`

```javascript
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  build: {
    rollupOptions: {
      input: {
        console: 'console.html',
        history: 'history.html',
      },
    },
  },
});
```

### `console.html`

```html
<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rylees Console</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/apps/console/main.js"></script>
  </body>
</html>
```

### `history.html`

```html
<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Release History</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/apps/history/main.js"></script>
  </body>
</html>
```

---

## 5. Tailwind Configuration

### `tailwind.config.js`

```javascript
export default {
  content: ['./src/**/*.{vue,js}', './*.html'],
  theme: { extend: {} },
  plugins: [],
};
```

### `postcss.config.js`

```javascript
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
};
```

Create `src/assets/main.css` with:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

Import this in both `console/main.js` and `history/main.js`:

```javascript
import '../../assets/main.css';
```

---

## 6. Shared API Client (`src/shared/api.js`)

This is the single source of truth for all HTTP calls. Both apps import from this file.

### Axios instance

```javascript
import axios from 'axios';

const BASE_URL = 'https://api.rylees.ai/v1';

export const apiClient = axios.create({
  baseURL: BASE_URL,
});

// Inject token on every request
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('rylees_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// On 401, clear auth and redirect to login
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('rylees_token');
      localStorage.removeItem('rylees_user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);
```

### Auth API functions

```javascript
export const login = (username, password) =>
  apiClient.post('/auth/login', { username, password });
// Response 200: { token_type, access_token, expires_in, user: { id, username, is_active, profile, organisation } }

export const logout = () =>
  apiClient.post('/auth/logout');
// Response 204

export const register = (payload) =>
  apiClient.post('/users/register', payload);
// payload: { username, password, profile: { firstname, lastname }, organisation: { name, street, postcode, city, website, email } }
// Response 201: { user: { id, username, is_active, activated_at, created_at }, profile, organisation: { id, name, slug } }

export const activate = (token) =>
  apiClient.get('/users/activate', { params: { token } });
// Response 200: { message: "Account activated successfully." }

export const getMe = () =>
  apiClient.get('/users/me');
// Response 200: { id, username, is_active, activated_at, api_key, profile: { id, firstname, lastname }, organisation: { id, slug, name, street, postcode, city, website, email } }

export const updateMe = (payload) =>
  apiClient.patch('/users/me', payload);
// payload (all optional): { profile: { firstname, lastname }, organisation: { name, street, postcode, city, website, email }, current_password, new_password }
// Response 200: same as GET /users/me

export const deleteMe = () =>
  apiClient.delete('/users/me');
// Response 204
```

### Customer API functions

```javascript
export const getCustomers = (page = 1, perPage = 20) =>
  apiClient.get('/customers', { params: { page, per_page: perPage } });
// Response 200: {
//   data: [{ id, description, projects_count, organisation: { id, slug, name, street, postcode, city, website, email }, main_contact: { id, firstname, lastname, email } | null, industry: { id, name } | null, created_at, updated_at }],
//   meta: { current_page, last_page, total }
// }

export const getCustomer = (id) =>
  apiClient.get(`/customers/${id}`);
// Response 200: {
//   id, description,
//   organisation: { id, slug, name, street, postcode, city, website, email },
//   industry: { id, name } | null,
//   contacts: [{ id, firstname, lastname, email }],
//   main_contact: { id, firstname, lastname, email } | null,
//   projects: [{ id, name, key }]
// }

export const createCustomer = (payload) =>
  apiClient.post('/customers', payload);
// payload: { organisation: { name, ... }, industry_id?, description?, main_contact?: { firstname, lastname, email } }
// Response 201: { id, organisation: { id, name, slug }, created_at }

export const updateCustomer = (id, payload) =>
  apiClient.patch(`/customers/${id}`, payload);
// payload (all optional): { organisation: { name, street, postcode, city, website, email }, industry_id, description }
// Response 200: same as GET /customers/{id}

export const deleteCustomer = (id) =>
  apiClient.delete(`/customers/${id}`);
// Cascades server-side: every contact, every project (and that project's release
// history/notes) is deleted along with the customer. Response 204.
```

### Contact API functions

```javascript
export const createContact = (customerId, payload) =>
  apiClient.post(`/customers/${customerId}/contacts`, payload);
// payload: { firstname, lastname, email }
// Response 201: { id, firstname, lastname, email }

export const updateContact = (customerId, contactId, payload) =>
  apiClient.patch(`/customers/${customerId}/contacts/${contactId}`, payload);
// payload (all optional): { firstname, lastname, email }
// Response 200: { id, firstname, lastname, email }

export const deleteContact = (customerId, contactId) =>
  apiClient.delete(`/customers/${customerId}/contacts/${contactId}`);
// Response 204
```

### Project API functions

```javascript
export const getProjects = (customerId) =>
  apiClient.get(`/customers/${customerId}/projects`);
// Response 200: {
//   data: [{ id, name, key, description, token, llm: { temperature, tonality }, created_at }]
// }

export const getProject = (customerId, id) =>
  apiClient.get(`/customers/${customerId}/projects/${id}`);
// Response 200: {
//   id, name, key, description, token,
//   customer: { id, name, industry, organisation_slug },
//   llm: { temperature, tonality },
//   created_at, updated_at
// }

export const createProject = (customerId, payload) =>
  apiClient.post(`/customers/${customerId}/projects`, payload);
// payload: { name, description?, llm_tonality_id, llm_temperature_id }
// Response 201: { id, name, key, token, created_at }

export const updateProject = (customerId, id, payload) =>
  apiClient.patch(`/customers/${customerId}/projects/${id}`, payload);
// payload (all optional): { name, description, llm_tonality_id, llm_temperature_id }
// Response 200: same as GET /customers/{id}/projects/{id}
```

### Public Release History API functions

```javascript
export const getReleaseHistory = (customerSlug, projectKey) =>
  apiClient.get(`/public/release-history/${customerSlug}/${projectKey}`);
// Response 200: {
//   project: { id, name, key },
//   items: [{ id, version, body, publishedAt }]
// }

export const translateReleaseHistory = (customerSlug, projectKey, language) =>
  apiClient.get(`/public/release-history/${customerSlug}/${projectKey}/translate`, {
    params: { language },
  });
// language: 'de' | 'en' | 'fr'
// Response 200: { language, items: [{ id, version, body }] }
```

### Reference data API functions

```javascript
export const getIndustries = () =>
  apiClient.get('/ref/industries');
// Response 200: { items: [{ id, name }] }

export const getLlmTonalities = () =>
  apiClient.get('/ref/llm-tonalities');
// Response 200: { items: [{ id, name }] }

export const getLlmTemperatures = () =>
  apiClient.get('/ref/llm-temperatures');
// Response 200: { items: [{ id, name, value }] }
```

---

## 7. Pinia Stores

### 7.1 `useAuthStore` (`console/stores/auth.js`)

```javascript
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { login as apiLogin, logout as apiLogout, getMe } from '../../../shared/api.js';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const token = ref(null);

  const isAuthenticated = computed(() => !!token.value);

  async function login(username, password) {
    const response = await apiLogin(username, password);
    const data = response.data;
    token.value = data.access_token;
    user.value = data.user;
    localStorage.setItem('rylees_token', data.access_token);
    localStorage.setItem('rylees_user', JSON.stringify(data.user));
  }

  async function logout() {
    try { await apiLogout(); } catch {}
    token.value = null;
    user.value = null;
    localStorage.removeItem('rylees_token');
    localStorage.removeItem('rylees_user');
  }

  async function restoreFromLocalStorage() {
    const storedToken = localStorage.getItem('rylees_token');
    if (!storedToken) return false;
    token.value = storedToken;
    try {
      const response = await getMe();
      user.value = response.data;
      localStorage.setItem('rylees_user', JSON.stringify(response.data));
      return true;
    } catch {
      token.value = null;
      user.value = null;
      localStorage.removeItem('rylees_token');
      localStorage.removeItem('rylees_user');
      return false;
    }
  }

  function updateUser(userData) {
    user.value = userData;
    localStorage.setItem('rylees_user', JSON.stringify(userData));
  }

  return { user, token, isAuthenticated, login, logout, restoreFromLocalStorage, updateUser };
});
```

**`UserObject` shape stored in state:**

```json
{
  "id": "...",
  "username": "jane@example.com",
  "is_active": true,
  "activated_at": "...",
  "api_key": "<64-char-key>",
  "profile": { "id": "...", "firstname": "Jane", "lastname": "Doe" },
  "organisation": {
    "id": "...",
    "slug": "doe-digital-gmbh",
    "name": "Doe Digital GmbH",
    "street": "...",
    "postcode": "...",
    "city": "...",
    "website": "...",
    "email": "..."
  }
}
```

`localStorage` keys: `rylees_token` (string), `rylees_user` (JSON string).

### 7.2 `useCustomersStore` (`console/stores/customers.js`)

```javascript
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { getCustomers, getCustomer, createCustomer, updateCustomer, deleteCustomer } from '../../../shared/api.js';

export const useCustomersStore = defineStore('customers', () => {
  const customers = ref([]);
  const currentCustomer = ref(null);
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

  async function fetchCustomers(page = 1, perPage = 20) {
    const response = await getCustomers(page, perPage);
    customers.value = response.data.data;
    pagination.value = response.data.meta;
  }

  async function fetchCustomer(id) {
    const response = await getCustomer(id);
    currentCustomer.value = response.data;
    return response.data;
  }

  async function storeCustomer(payload) {
    return createCustomer(payload);
  }

  async function patchCustomer(id, payload) {
    const response = await updateCustomer(id, payload);
    currentCustomer.value = response.data;
    return response.data;
  }

  async function removeCustomer(id) {
    await deleteCustomer(id);
    if (currentCustomer.value?.id === id) currentCustomer.value = null;
  }

  return { customers, currentCustomer, pagination, fetchCustomers, fetchCustomer, storeCustomer, patchCustomer, removeCustomer };
});
```

### 7.3 `useProjectsStore` (`console/stores/projects.js`)

```javascript
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { getProjects, getProject, createProject, updateProject } from '../../../shared/api.js';

export const useProjectsStore = defineStore('projects', () => {
  const projects = ref([]);
  const currentProject = ref(null);

  async function fetchProjects(customerId) {
    const response = await getProjects(customerId);
    projects.value = response.data.data;
  }

  async function fetchProject(customerId, id) {
    const response = await getProject(customerId, id);
    currentProject.value = response.data;
    return response.data;
  }

  async function storeProject(customerId, payload) {
    return createProject(customerId, payload);
  }

  async function patchProject(customerId, id, payload) {
    const response = await updateProject(customerId, id, payload);
    currentProject.value = response.data;
    return response.data;
  }

  return { projects, currentProject, fetchProjects, fetchProject, storeProject, patchProject };
});
```

---

## 8. Developer Console — Main and Router

### `console/main.js`

```javascript
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router/index.js';
import '../../assets/main.css';

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.mount('#app');
```

### `console/App.vue`

On app mount, restore auth from localStorage before the router runs:

```vue
<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from './stores/auth.js';
import { useRouter } from 'vue-router';

const auth = useAuthStore();
const router = useRouter();

onMounted(async () => {
  await auth.restoreFromLocalStorage();
});
</script>

<template>
  <RouterView />
</template>
```

### `console/router/index.js`

```javascript
import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/login',    name: 'login',    component: () => import('../views/LoginView.vue'),    meta: { requiresAuth: false } },
  { path: '/register', name: 'register', component: () => import('../views/RegisterView.vue'), meta: { requiresAuth: false } },
  { path: '/activate', name: 'activate', component: () => import('../views/ActivateView.vue'), meta: { requiresAuth: false } },
  { path: '/dashboard',name: 'dashboard',component: () => import('../views/DashboardView.vue'),meta: { requiresAuth: true } },
  { path: '/customers',                name: 'customers',       component: () => import('../views/CustomersView.vue'),      meta: { requiresAuth: true } },
  { path: '/customers/new',            name: 'customer-create', component: () => import('../views/CustomerCreateView.vue'), meta: { requiresAuth: true } },
  { path: '/customers/:id',            name: 'customer-detail', component: () => import('../views/CustomerDetailView.vue'), meta: { requiresAuth: true } },
  { path: '/customers/:id/edit',       name: 'customer-edit',   component: () => import('../views/CustomerEditView.vue'),   meta: { requiresAuth: true } },
  { path: '/customers/:customerId/projects/new',      name: 'project-create', component: () => import('../views/ProjectCreateView.vue'), meta: { requiresAuth: true } },
  { path: '/customers/:customerId/projects/:id',      name: 'project-detail', component: () => import('../views/ProjectDetailView.vue'), meta: { requiresAuth: true } },
  { path: '/customers/:customerId/projects/:id/edit', name: 'project-edit',   component: () => import('../views/ProjectEditView.vue'),   meta: { requiresAuth: true } },
  { path: '/account',  name: 'account',  component: () => import('../views/AccountView.vue'), meta: { requiresAuth: true } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to) => {
  const auth = useAuthStore();
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }
});

export default router;
```

---

## 9. Developer Console — View Specifications

> **Visual design:** the app shell (3-column layout), navigation, auth pages, card-list overviews, form/validation patterns, and error pages are specified in [`./DESIGN-SPEC-DC.md`](./DESIGN-SPEC-DC.md). This section covers the functional wiring; consult the design spec for presentation and for the deviations in its §11 (card-list overviews supersede AC-FE-08 tables; "Customers" labelled "Organisations"; added registration password/org fields; plus backend-dependent items: global `/projects` endpoint and a new project `language` field).

For every view, implement exactly the described data flow and user interactions. Do not add functionality beyond what is described.

### 9.1 LoginView

**File:** `views/LoginView.vue`

**Data flow:**
- Reactive form state: `username` (string), `password` (string), `error` (string|null), `loading` (boolean)

**On submit:**
1. Set `loading = true`, `error = null`
2. Call `useAuthStore().login(username, password)`
3. On success: redirect to `route.query.redirect || '/dashboard'`
4. On 401 error: set `error = 'Invalid email or password.'`
5. On 403 (inactive_user): set `error = 'Account not yet activated. Please check your email.'`
6. Set `loading = false`

**Template must include:** email input, password input, submit button, inline error display if `error` is set, link to `/register`.

### 9.2 RegisterView

**File:** `views/RegisterView.vue`

**Data flow:**
- Reactive form with three sections: Credentials (`username`, `password`), Personal (`firstname`, `lastname`), Organisation (`name`, `street`, `postcode`, `city`, `website`, `email`)
- State: `fieldErrors` (object keyed by field path), `successMessage` (string|null), `loading` (boolean)

**On submit:**
1. Build payload matching `POST /users/register` request shape
2. Call `register(payload)`
3. On 201: set `successMessage = 'Account created. Please check your email to activate your account.'`; hide form
4. On 422: map `error.response.data.errors` into `fieldErrors`; display each error next to its field

**Field error keys** from API use dot-notation (e.g. `profile.firstname`, `organisation.name`). Display the corresponding error message below the relevant input.

### 9.3 ActivateView

**File:** `views/ActivateView.vue`

**On mount:**
1. Read `route.query.token`
2. Call `activate(token)`
3. On 200: show success message `'Your account has been activated.'` with a link to `/login`
4. On 404: show error message `'Activation link is invalid or has already been used.'`

No form inputs. Triggered automatically on mount.

### 9.4 DashboardView

**File:** `views/DashboardView.vue`

**On mount:**
1. Read `auth.user.profile.firstname` for the greeting
2. Call `useCustomersStore().fetchCustomers(1, 100)` to load all customers (max 100 is sufficient for counts)

**Template must display:**
- Heading: `"Welcome back, {firstname}!"`
- Total customers: `pagination.total` (from `meta.total` on the response)
- Total projects: sum of `customers[i].projects_count` across all loaded customer items

**Example:**

```vue
<template>
  <h1>Welcome back, {{ auth.user.profile.firstname }}!</h1>
  <p>Customers: {{ pagination.total }}</p>
  <p>Projects: {{ totalProjects }}</p>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { useCustomersStore } from '../stores/customers.js';

const auth = useAuthStore();
const customersStore = useCustomersStore();

onMounted(() => customersStore.fetchCustomers(1, 100));

const totalProjects = computed(() =>
  customersStore.customers.reduce((sum, c) => sum + (c.projects_count || 0), 0)
);
const pagination = computed(() => customersStore.pagination);
</script>
```

### 9.5 CustomersView

**File:** `views/CustomersView.vue`

**On mount:** Call `fetchCustomers(page, perPage)`.

**Template must include:**
- Table with columns: **Name** (`organisation.name`), **Industry** (`industry.name || '—'`), **City** (`organisation.city || '—'`), **Projects** (`projects_count`)
- Each row links to `/customers/{id}` (the customer detail view)
- "New Customer" button links to `/customers/new`
- Pagination controls: previous/next buttons; show current page number; use `pagination.current_page`, `pagination.last_page`

On page change: call `fetchCustomers(newPage, perPage)`.

### 9.6 CustomerCreateView

**File:** `views/CustomerCreateView.vue`

**On mount:**
- Call `getIndustries()` and populate industry dropdown

**Form fields:**
- Organisation: name (required), street, postcode, city, website, email
- Industry: dropdown from `getIndustries()` response; optional
- Description: textarea; optional
- Main contact (optional block): firstname, lastname, email — all required if the block is shown

**On submit:**
- Build payload: `{ organisation: {...}, industry_id, description, main_contact: { firstname, lastname, email } }` (include `main_contact` only if fields are filled)
- Call `createCustomer(payload)`
- On 201: redirect to `/customers/{response.data.id}`
- On 422: show inline field errors

### 9.7 CustomerDetailView

**File:** `views/CustomerDetailView.vue`

**On mount:** Call `fetchCustomer(route.params.id)`.

**Template must include:**

**Info panel:** organisation name, slug, city, website, email, industry.

**Contacts section:**
- List all contacts from `currentCustomer.contacts`
- Mark the main contact (compare `contact.id === currentCustomer.main_contact?.id`)
- "Add contact" button → shows an inline form (not a separate route) with firstname, lastname, email
  - On submit: call `createContact(customerId, payload)` → reload customer
- Each contact has an "Edit" button → inline edit form
  - On save: call `updateContact(customerId, contactId, payload)` → reload customer
- Each contact has a "Delete" button → confirm first → call `deleteContact(customerId, contactId)` → reload customer

**Projects section:**
- List all projects from `currentCustomer.projects` with name and key
- Each project links to `/customers/{id}/projects/{projectId}`
- "Add Project" button links to `/customers/{id}/projects/new`

**"Edit customer" button** links to `/customers/{id}/edit`.

**Delete customer:** a "Delete customer" button, pinned in the layout's `footer-actions` slot at the bottom of the screen, styled `bg-danger` (`rgb(223 94 112)` / `#df5e70`). Clicking it opens a `ConfirmDialog` warning that the customer's contacts, projects and release notes will also be deleted; confirming calls `removeCustomer(customerId)` (store action wrapping `deleteCustomer(id)`) and redirects to `/customers`. A failed delete shows an error `Notification` instead of navigating away.

### 9.8 CustomerEditView

**File:** `views/CustomerEditView.vue`

**On mount:** Call `fetchCustomer(route.params.id)` to pre-fill the form.

**Form fields:** same as CustomerCreateView minus the contact block (contacts are managed in the detail view).

**On submit:**
- Payload: `{ organisation: { name, street, postcode, city, website, email }, industry_id, description }`
- Call `patchCustomer(id, payload)`
- On 200: redirect to `/customers/{id}`
- On 422: show inline field errors

### 9.9 ProjectCreateView

**File:** `views/ProjectCreateView.vue`

**On mount:**
- Call `getLlmTonalities()` and `getLlmTemperatures()` to populate dropdowns

**Form fields:**
- Name: required
- Description: optional textarea
- LLM Tonality: required dropdown (from `getLlmTonalities()`, display `item.name`)
- LLM Temperature: required dropdown (from `getLlmTemperatures()`, display `item.name` + value, e.g. `"balanced (0.5)"`)

**On submit:**
- Payload: `{ name, description, llm_tonality_id, llm_temperature_id }`
- Call `storeProject(route.params.customerId, payload)`
- On 201: redirect to `/customers/{customerId}/projects/{response.data.id}`
- On 422: show inline field errors

### 9.10 ProjectDetailView

**File:** `views/ProjectDetailView.vue`

**On mount:**
1. Call `fetchProject(route.params.customerId, route.params.id)`
2. Using the returned `project.customer.organisation_slug` and `project.key`, call `getReleaseHistory(organisation_slug, project.key)` to load release notes

**Template must include:**

**Project info panel:**
- Name, description
- LLM: tonality (`project.llm.tonality`), temperature (`project.llm.temperature`)

**API Token section:**
```vue
<code>{{ project.token }}</code>
<button @click="copyToken">Copy to clipboard</button>
```

```javascript
function copyToken() {
  navigator.clipboard.writeText(project.token);
}
```

**Release history table:**
- Columns: **Version** (`v{version}`), **Published** (formatted date), **Summary** (first 100 chars of `body` + `…` if longer)
- Order: newest first (API already returns newest first)
- Date format: `new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(note.publishedAt))`

Do NOT use `v-html` to render `body`. Use text interpolation (`{{ body }}`).

### 9.11 ProjectEditView

**File:** `views/ProjectEditView.vue`

**On mount:**
- Call `fetchProject(route.params.customerId, route.params.id)` to pre-fill form
- Call `getLlmTonalities()` and `getLlmTemperatures()` for dropdowns

**Form fields:** name, description, llm_tonality_id, llm_temperature_id.

**NOT included:** token or key fields — these are read-only.

**On submit:**
- Payload: `{ name, description, llm_tonality_id, llm_temperature_id }`
- Call `patchProject(customerId, id, payload)`
- On 200: redirect to `/customers/{customerId}/projects/{id}`
- On 422: show inline field errors

### 9.12 AccountView

**File:** `views/AccountView.vue`

**On mount:** Read user data from `auth.user`.

**Form sections:**

**Profile:** firstname, lastname.

**Organisation:** name, street, postcode, city, website, email.

**Password change:** current_password, new_password, confirm_new_password.

**Password validation (client-side, before API call):**
- If `new_password` is non-empty and `new_password !== confirm_new_password`: display inline error `'Passwords do not match'` on `confirm_new_password` field; do not call API.
- If `new_password` is non-empty, `current_password` must also be non-empty; if missing: display inline error `'Current password is required to change your password'`.

**On submit:**
1. Build payload with profile and organisation fields (always include these)
2. If `new_password` is non-empty: also include `current_password` and `new_password`
3. Call `updateMe(payload)`
4. On 200: call `auth.updateUser(response.data)`; show success message `'Profile updated successfully.'`
5. On 422 with `current_password` error in `errors`: display `'Current password is incorrect.'` on the `current_password` field
6. On other 422 errors: display inline per-field

---

## 10. Release History App

> **Visual design:** the look, layout, icons, timeline/accordion faces, and flip interaction for this app are specified in [`./DESIGN-SPEC-RH.md`](./DESIGN-SPEC-RH.md). This section covers the functional wiring; consult the design spec for presentation (and for the deviations in its §8: relative dates, deferred language switcher, dropped author).

### `history/main.js`

Extract slug and key before mounting. Provide them to the app via `app.provide`.

```javascript
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router/index.js';
import '../../assets/main.css';

// Extract customer slug from subdomain: "acme-ltd.rylees.ai" → "acme-ltd"
const hostname = window.location.hostname;
const parts = hostname.split('.');
const customerSlug = parts[0];

// Extract project key from first path segment: "/member-portal" → "member-portal"
const projectKey = window.location.pathname.replace(/^\//, '').split('/')[0];

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.provide('customerSlug', customerSlug);
app.provide('projectKey', projectKey);
app.mount('#app');
```

### `history/App.vue`

```vue
<template>
  <RouterView />
</template>
```

### `history/router/index.js`

```javascript
import { createRouter, createWebHistory } from 'vue-router';

const routes = [
  { path: '/:projectKey', name: 'release-history', component: () => import('../views/ReleaseHistoryView.vue') },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/NotFoundView.vue') },
];

export default createRouter({
  history: createWebHistory(),
  routes,
});
```

### `history/views/ReleaseHistoryView.vue`

**On mount:**
1. Inject `customerSlug` and `projectKey` via `inject('customerSlug')` / `inject('projectKey')`
2. Call `getReleaseHistory(customerSlug, projectKey)`
3. On 200: store `project` and `items` (original DE bodies) in reactive state; set `document.title = `${project.name} — Release History``
4. On 404: redirect to `{ name: 'not-found' }`

**Reactive state:**
```javascript
const project = ref(null);
const originalItems = ref([]);   // original DE items from API, never overwritten
const displayItems = ref([]);    // items currently shown (may be translated)
const activeLanguage = ref('de');
const translating = ref(false);
```

**Timeline rendering (newest first):**

Each item shows:
- Version badge: `v{{ item.version }}`
- Date: formatted using `Intl.DateTimeFormat` — see locale map below
- Body text: `{{ item.body }}` — text interpolation only; NO `v-html`

**Date formatting locale map:**
```javascript
const localeMap = { de: 'de-DE', en: 'en-GB', fr: 'fr-FR' };

function formatDate(isoString, language) {
  return new Intl.DateTimeFormat(localeMap[language], {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date(isoString));
}
```

**Language switcher in page header:**

Three buttons: **DE**, **EN**, **FR**. Apply active styling to the active language button.

```javascript
async function switchLanguage(lang) {
  if (lang === activeLanguage.value) return;
  if (lang === 'de') {
    // Restore original bodies — no API call
    displayItems.value = originalItems.value;
    activeLanguage.value = 'de';
    return;
  }
  // Show loading skeleton per item
  translating.value = true;
  try {
    const response = await translateReleaseHistory(customerSlug, projectKey, lang);
    // Merge translated bodies into display items (preserve version and publishedAt from original)
    const translatedMap = Object.fromEntries(response.data.items.map(i => [i.id, i.body]));
    displayItems.value = originalItems.value.map(item => ({
      ...item,
      body: translatedMap[item.id] ?? item.body,
    }));
    activeLanguage.value = lang;
  } finally {
    translating.value = false;
  }
}
```

**Loading skeleton:** while `translating` is `true`, replace each body with a grey animated placeholder:

```vue
<template v-if="translating">
  <div class="animate-pulse bg-gray-200 rounded h-4 w-full mb-2"></div>
  <div class="animate-pulse bg-gray-200 rounded h-4 w-3/4"></div>
</template>
<template v-else>
  <p>{{ item.body }}</p>
</template>
```

**Page title:** set in `onMounted` after data loads:
```javascript
document.title = `${project.value.name} — Release History`;
```

### `history/views/NotFoundView.vue`

Display a simple "Project not found" message. No API calls.

```vue
<template>
  <div>
    <h1>Project not found</h1>
    <p>The requested release history could not be found.</p>
  </div>
</template>
```

---

## 11. Security Constraints

- **NO `v-html`** with any value originating from API responses or user input. Use text interpolation (`{{ }}`) for all free-form content, including `body`, `description`, names, etc.
- Axios request interceptor must NOT log the token to `console`.
- `localStorage` must use exactly these key names: `rylees_token` (raw string), `rylees_user` (JSON-serialized user object).
- The 401 interceptor clears both localStorage keys before redirecting. It must not loop (do not make further API calls inside the interceptor).

---

## 12. Testing Requirements

Framework: Jest 29 + `@vue/test-utils` 2. Mock Axios using `jest.mock('../../../shared/api.js')`.

### Test files and what each must cover

**`tests/console/LoginView.test.js`** (AC-FE-02, AC-FE-03)
- `renders email and password inputs`
- `on 401 displays invalid credentials error`
- `on 403 inactive_user displays activation message`
- `on success redirects to dashboard`

**`tests/console/RegisterView.test.js`** (AC-FE-05)
- `on 201 shows success message and hides form`
- `on 422 displays inline field errors next to each field`

**`tests/console/ActivateView.test.js`** (AC-FE-06)
- `on mount reads token from query string and calls activate`
- `on 200 shows success message and login link`
- `on 404 shows error message`

**`tests/console/DashboardView.test.js`** (AC-FE-07)
- `displays user firstname in welcome heading`
- `displays total customer count from meta.total`
- `sums projects_count across customers for total project count`

**`tests/console/CustomersView.test.js`** (AC-FE-08)
- `renders customer table with name, industry, city, and projects_count columns`
- `renders new customer button`
- `pagination controls call fetchCustomers with updated page`

**`tests/console/CustomerDetailView.test.js`** (AC-FE-08)
- `renders customer info, contacts list, and projects list`
- `projects list entries link to ProjectDetailView`

**`tests/console/ProjectDetailView.test.js`** (AC-FE-09)
- `displays project name, description, and LLM settings`
- `displays token in code block`
- `copy button calls navigator.clipboard.writeText with token`
- `renders release history table with version, date, and truncated body`

**`tests/console/AccountView.test.js`** (AC-FE-10)
- `client-side validates that new_password matches confirm_new_password before calling API`
- `on 422 current_password error shows inline error on that field`

**`tests/console/AuthStore.test.js`** (AC-FE-02, AC-FE-04)
- `login stores token and user in state and localStorage`
- `logout clears state and localStorage`
- `restoreFromLocalStorage calls getMe and restores state on 200`
- `restoreFromLocalStorage clears state and localStorage on non-200`

**`tests/console/RouterGuard.test.js`** (AC-FE-03)
- `unauthenticated access to auth-required route redirects to login`
- `login then public route is accessible`

**`tests/history/ReleaseHistoryView.test.js`** (AC-FE-11, AC-FE-12, AC-FE-13)
- `extracts customerSlug and projectKey from injected values`
- `calls getReleaseHistory on mount`
- `sets document.title to project name`
- `renders timeline entries with version badge and date`
- `on 404 renders NotFoundView`
- `switching to EN calls translate endpoint and replaces bodies`
- `switching back to DE restores original bodies without API call`
- `shows loading skeleton while translate request is in flight`
- `body text rendered with text interpolation, not v-html`

---

## 13. Acceptance Criteria

### AC-FE-01 — Build and entry points

- `vite build` completes without errors and produces two separate bundles: one for the Developer Console (`console.html`) and one for the Release History (`history.html`).
- Neither bundle contains code from the other entry point.

### AC-FE-02 — Authentication persistence (Developer Console)

- After a successful login, the token and user object are stored in `localStorage` under keys `rylees_token` and `rylees_user`, and in the Pinia `useAuthStore`.
- On a hard page reload, the app restores auth from `localStorage`, calls `GET /users/me` to validate, and lands on the previously visited route rather than redirecting to `/login`.
- If `GET /users/me` returns a non-`200` response during restore, the stored auth is cleared and the user is redirected to `/login`.

### AC-FE-03 — Route guards

- Navigating to any auth-required route while unauthenticated redirects to `/login`.
- After login, the user is redirected to the originally requested route (or `/dashboard` if none).
- `/login`, `/register`, and `/activate` are accessible without a token.

### AC-FE-04 — Axios auth header

- Every Axios request made while a token is stored includes `Authorization: Bearer <token>`.
- Requests made before login or after logout do not include the header.

### AC-FE-05 — Registration flow

- Submitting the `RegisterView` form with valid data calls `POST /users/register` and displays: "Account created. Please check your email to activate your account."
- Validation errors returned by the API are shown inline next to the relevant fields.

### AC-FE-06 — Activation flow

- `ActivateView` reads `?token=` from the query string on mount and calls `GET /users/activate?token=...` automatically.
- A success response renders a success message and a link to `/login`.
- A `404` response renders a clear error message.

### AC-FE-07 — Dashboard

- `DashboardView` displays the authenticated user's first name and the total count of their customers and projects.

### AC-FE-08 — Customer list and detail

- `CustomersView` renders a table with columns for name, industry, city, and project count, including a "New Customer" button.
- `CustomerDetailView` shows the customer info panel, the contacts list (with add/edit/delete actions), and a projects list where each project links to `ProjectDetailView`.

### AC-FE-09 — Project detail

- `ProjectDetailView` displays the project name, description, and LLM settings.
- The `token` is displayed in a `<code>` block with a functional "Copy to clipboard" button.
- The release history table lists each entry with its version, publication date, and the first 100 characters of the body.

### AC-FE-10 — Account settings

- `AccountView` allows updating `firstname`, `lastname`, and all organisation fields.
- Submitting a new password without `current_password` shows a validation error before calling the API.
- A mismatched `current_password` returned by the API is displayed as an inline error.

### AC-FE-11 — Release History app — data loading

- On mount, `customerSlug` is extracted from the subdomain of `window.location.hostname` and `projectKey` from the first path segment.
- `GET /v1/public/release-history/{customerSlug}/{projectKey}` is called automatically and the response is rendered as a vertical timeline, newest entry first.
- The page `<title>` is set to `{project.name} — Release History`.
- An unresolvable slug/key combination renders `NotFoundView` with a "Project not found" message.

### AC-FE-12 — Release History app — timeline entries

- Each entry shows a version badge (e.g. `v1.3.0`), a publication date formatted as `DD. MMMM YYYY` in the active language, and the full body text.

### AC-FE-13 — Language switcher

- The page header contains **DE**, **EN**, and **FR** options; **DE** is active by default.
- Selecting a non-active language calls the translate endpoint and replaces each body with the translated text; a loading skeleton is shown per entry while the request is in flight.
- Switching back to **DE** restores the original untranslated bodies without a new API call.

### AC-FE-14 — No `v-html` with user content

- No Vue template uses `v-html` to render any value that originates from user input or API responses containing free-form text.
