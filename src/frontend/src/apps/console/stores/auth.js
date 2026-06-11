import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { login as apiLogin, logout as apiLogout, getMe } from '../../../shared/api.js';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const token = ref(null);
  // Tracks whether a localStorage restore was already attempted (so the router
  // guard only restores once, before the first protected navigation resolves).
  const initialized = ref(false);

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
    try {
      await apiLogout();
    } catch {
      /* ignore network errors on logout */
    }
    token.value = null;
    user.value = null;
    localStorage.removeItem('rylees_token');
    localStorage.removeItem('rylees_user');
  }

  async function restoreFromLocalStorage() {
    initialized.value = true;
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

  return {
    user,
    token,
    initialized,
    isAuthenticated,
    login,
    logout,
    restoreFromLocalStorage,
    updateUser,
  };
});
