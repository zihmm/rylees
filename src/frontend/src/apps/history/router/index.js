import { createRouter, createWebHistory } from 'vue-router';

const routes = [
  {
    path: '/:projectKey',
    name: 'release-history',
    component: () => import('../views/ReleaseHistoryView.vue'),
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('../views/NotFoundView.vue'),
  },
];

export default createRouter({
  // Dev serves this app under /history/; production serves it at the domain root.
  history: createWebHistory(import.meta.env.DEV ? '/history/' : '/'),
  routes,
});
