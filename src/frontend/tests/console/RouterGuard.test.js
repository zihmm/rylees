import { createPinia, setActivePinia } from 'pinia';
import router from '../../src/apps/console/router/index.js';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

// The guard reads `useAuthStore()` at navigation time against the active Pinia.
// A fresh Pinia per test resets auth state (token + the `initialized` flag),
// so each navigation re-runs the localStorage restore.
beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  localStorage.clear();
});

describe('console router guard', () => {
  test('unauthenticated access to an auth-required route redirects to /login', async () => {
    await router.push('/dashboard');
    await router.isReady();
    expect(router.currentRoute.value.name).toBe('login');
    expect(router.currentRoute.value.query.redirect).toBe('/dashboard');
  });

  test('/login is accessible without a token', async () => {
    await router.push('/login');
    await router.isReady();
    expect(router.currentRoute.value.name).toBe('login');
  });

  test('public /register route is accessible without a token', async () => {
    await router.push('/register');
    await router.isReady();
    expect(router.currentRoute.value.name).toBe('register');
  });

  test('authenticated user (valid token) reaches a protected route', async () => {
    localStorage.setItem('rylees_token', 'tok');
    api.getMe.mockResolvedValue({ data: { id: 'u1', profile: { firstname: 'Marc' } } });
    await router.push('/dashboard');
    await router.isReady();
    expect(api.getMe).toHaveBeenCalled();
    expect(router.currentRoute.value.name).toBe('dashboard');
  });
});
