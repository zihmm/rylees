import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from '../../src/apps/console/stores/auth.js';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  localStorage.clear();
});

describe('AuthStore', () => {
  test('login stores token + user in state and localStorage', async () => {
    const user = { id: 'u1', profile: { firstname: 'Marc' } };
    api.login.mockResolvedValue({ data: { access_token: 'tok', user } });
    const auth = useAuthStore();

    await auth.login('a@b.com', 'pw');

    expect(auth.token).toBe('tok');
    expect(auth.user).toEqual(user);
    expect(auth.isAuthenticated).toBe(true);
    expect(localStorage.getItem('rylees_token')).toBe('tok');
    expect(JSON.parse(localStorage.getItem('rylees_user'))).toEqual(user);
  });

  test('logout clears state and both localStorage keys', async () => {
    api.logout.mockResolvedValue({ data: {} });
    const auth = useAuthStore();
    auth.token = 'tok';
    auth.user = { id: 'u1' };
    localStorage.setItem('rylees_token', 'tok');
    localStorage.setItem('rylees_user', '{"id":"u1"}');

    await auth.logout();

    expect(auth.token).toBeNull();
    expect(auth.user).toBeNull();
    expect(localStorage.getItem('rylees_token')).toBeNull();
    expect(localStorage.getItem('rylees_user')).toBeNull();
  });

  test('restoreFromLocalStorage calls getMe and restores on 200', async () => {
    const user = { id: 'u1', profile: { firstname: 'Marc' } };
    localStorage.setItem('rylees_token', 'stored-tok');
    api.getMe.mockResolvedValue({ data: user });
    const auth = useAuthStore();

    const ok = await auth.restoreFromLocalStorage();

    expect(api.getMe).toHaveBeenCalled();
    expect(ok).toBe(true);
    expect(auth.token).toBe('stored-tok');
    expect(auth.user).toEqual(user);
    expect(JSON.parse(localStorage.getItem('rylees_user'))).toEqual(user);
  });

  test('restoreFromLocalStorage clears state + storage on non-200', async () => {
    localStorage.setItem('rylees_token', 'stale-tok');
    localStorage.setItem('rylees_user', '{"id":"u1"}');
    api.getMe.mockRejectedValue({ response: { status: 401 } });
    const auth = useAuthStore();

    const ok = await auth.restoreFromLocalStorage();

    expect(ok).toBe(false);
    expect(auth.token).toBeNull();
    expect(auth.user).toBeNull();
    expect(localStorage.getItem('rylees_token')).toBeNull();
    expect(localStorage.getItem('rylees_user')).toBeNull();
  });

  test('restoreFromLocalStorage returns false when no token stored', async () => {
    const auth = useAuthStore();
    const ok = await auth.restoreFromLocalStorage();
    expect(ok).toBe(false);
    expect(api.getMe).not.toHaveBeenCalled();
    expect(auth.initialized).toBe(true);
  });
});
