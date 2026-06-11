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
  history: createWebHistory(),
  routes,
});
