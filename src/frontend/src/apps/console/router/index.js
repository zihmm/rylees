import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { requiresAuth: false } },
  { path: '/register', name: 'register', component: () => import('../views/RegisterView.vue'), meta: { requiresAuth: false } },
  { path: '/activate', name: 'activate', component: () => import('../views/ActivateView.vue'), meta: { requiresAuth: false } },
  { path: '/forgot-password', name: 'forgot-password', component: () => import('../views/ForgotPasswordView.vue'), meta: { requiresAuth: false } },
  { path: '/reset-password', name: 'reset-password', component: () => import('../views/ResetPasswordView.vue'), meta: { requiresAuth: false } },
  { path: '/dashboard', name: 'dashboard', component: () => import('../views/DashboardView.vue'), meta: { requiresAuth: true } },
  // "Customers" in the UI === customers in the API/spec.
  { path: '/customers', name: 'customers', component: () => import('../views/CustomersView.vue'), meta: { requiresAuth: true } },
  { path: '/customers/new', name: 'customer-create', component: () => import('../views/CustomerCreateView.vue'), meta: { requiresAuth: true } },
  { path: '/customers/:id', name: 'customer-detail', component: () => import('../views/CustomerDetailView.vue'), meta: { requiresAuth: true } },
  { path: '/customers/:id/edit', name: 'customer-edit', component: () => import('../views/CustomerEditView.vue'), meta: { requiresAuth: true } },
  { path: '/customers/:customerId/projects/new', name: 'project-create', component: () => import('../views/ProjectCreateView.vue'), meta: { requiresAuth: true } },
  { path: '/customers/:customerId/projects/:id', name: 'project-detail', component: () => import('../views/ProjectDetailView.vue'), meta: { requiresAuth: true } },
  { path: '/customers/:customerId/projects/:id/edit', name: 'project-edit', component: () => import('../views/ProjectEditView.vue'), meta: { requiresAuth: true } },
  // Global Projects overview (backed by the new aggregate /projects endpoint).
  { path: '/projects', name: 'projects', component: () => import('../views/ProjectsView.vue'), meta: { requiresAuth: true } },
  { path: '/projects/new', name: 'project-create-global', component: () => import('../views/ProjectCreateView.vue'), meta: { requiresAuth: true } },
  { path: '/account', name: 'account', component: () => import('../views/AccountView.vue'), meta: { requiresAuth: true } },
  { path: '/api-key', name: 'api-key', component: () => import('../views/ApiKeyView.vue'), meta: { requiresAuth: true } },
  { path: '/error', name: 'server-error', component: () => import('../views/ServerErrorView.vue'), meta: { requiresAuth: true } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/NotFoundView.vue'), meta: { requiresAuth: true } },
];

const router = createRouter({
  // Dev serves this app under /console/; production serves it at the domain root.
  history: createWebHistory(import.meta.env.DEV ? '/console/' : '/'),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  // Restore once from localStorage before resolving the first navigation,
  // so authenticated reloads don't bounce to /login (AC-FE-02).
  if (!auth.initialized) {
    await auth.restoreFromLocalStorage();
  }
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }
});

export default router;
