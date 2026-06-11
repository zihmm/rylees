import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router/index.js';
import '@fontsource/noto-sans/400.css';
import '@fontsource/noto-sans/600.css';
import '@fontsource/noto-sans/700.css';
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
