import axios from 'axios';

const BASE_URL = import.meta.env.VITE_API_BASE_URL;

export const apiClient = axios.create({
  baseURL: BASE_URL,
});

// Inject token on every request when present.
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('rylees_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// On 401, clear auth and redirect to login. Do not loop / no further API calls here.
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('rylees_token');
      localStorage.removeItem('rylees_user');
      if (error.config?.url !== '/auth/login' && window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

/* ---- Auth ---- */
export const login = (username, password) =>
  apiClient.post('/auth/login', { username, password });

export const logout = () => apiClient.post('/auth/logout');

export const register = (payload) => apiClient.post('/users/register', payload);

export const activate = (token) =>
  apiClient.get('/users/activate', { params: { token } });

export const forgotPassword = (username) =>
  apiClient.post('/auth/forgot-password', { username });

export const resetPassword = (token, password) =>
  apiClient.post('/auth/reset-password', { token, password });

export const getMe = () => apiClient.get('/users/me');

export const updateMe = (payload) => apiClient.patch('/users/me', payload);

export const deleteMe = () => apiClient.delete('/users/me');

/* ---- Customers (labelled "Organisations" in the console UI) ---- */
export const getCustomers = (page = 1, perPage = 20) =>
  apiClient.get('/customers', { params: { page, per_page: perPage } });

export const getCustomer = (id) => apiClient.get(`/customers/${id}`);

export const createCustomer = (payload) => apiClient.post('/customers', payload);

export const updateCustomer = (id, payload) =>
  apiClient.patch(`/customers/${id}`, payload);

/* ---- Contacts ---- */
export const createContact = (customerId, payload) =>
  apiClient.post(`/customers/${customerId}/contacts`, payload);

export const updateContact = (customerId, contactId, payload) =>
  apiClient.patch(`/customers/${customerId}/contacts/${contactId}`, payload);

export const deleteContact = (customerId, contactId) =>
  apiClient.delete(`/customers/${customerId}/contacts/${contactId}`);

/* ---- Projects (per-customer) ---- */
export const getProjects = (customerId) =>
  apiClient.get(`/customers/${customerId}/projects`);

export const getProject = (customerId, id) =>
  apiClient.get(`/customers/${customerId}/projects/${id}`);

export const createProject = (customerId, payload) =>
  apiClient.post(`/customers/${customerId}/projects`, payload);

export const updateProject = (customerId, id, payload) =>
  apiClient.patch(`/customers/${customerId}/projects/${id}`, payload);

export const deleteProject = (customerId, id) =>
  apiClient.delete(`/customers/${customerId}/projects/${id}`);

/* ---- Projects (global overview) ----
 * GET /projects — developer-wide project overview (SPEC §7.4). Returns every
 * non-deleted project across all of the developer's customers, ordered by
 * updated_at desc, as { data: [...] } (no pagination). */
export const getAllProjects = () => apiClient.get('/projects');

/* ---- Public Release History ---- */
export const getReleaseHistory = (customerSlug, projectKey) =>
  apiClient.get(`/public/release-history/${customerSlug}/${projectKey}`);

export const translateReleaseHistory = (customerSlug, projectKey, language) =>
  apiClient.get(`/public/release-history/${customerSlug}/${projectKey}/translate`, {
    params: { language },
  });

/* ---- Reference data ---- */
export const getIndustries = () => apiClient.get('/ref/industries');

export const getLlmTonalities = () => apiClient.get('/ref/llm-tonalities');

export const getLlmTemperatures = () => apiClient.get('/ref/llm-temperatures');
