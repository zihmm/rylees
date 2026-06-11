import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import LoginView from '../../src/apps/console/views/LoginView.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div><slot /></div>' };

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', name: 'login', component: LoginView },
      { path: '/dashboard', name: 'dashboard', component: Blank },
      { path: '/register', name: 'register', component: Blank },
    ],
  });
}

async function mountView() {
  const router = makeRouter();
  router.push('/login');
  await router.isReady();
  const wrapper = mount(LoginView, { global: { plugins: [router] } });
  await flushPromises();
  return { wrapper, router };
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  localStorage.clear();
});

describe('LoginView', () => {
  test('renders email and password inputs', async () => {
    const { wrapper } = await mountView();
    expect(wrapper.find('input[type="email"]').exists()).toBe(true);
    expect(wrapper.find('input[type="password"]').exists()).toBe(true);
  });

  test('401 shows "Invalid email or password."', async () => {
    api.login.mockRejectedValue({ response: { status: 401 } });
    const { wrapper } = await mountView();
    await wrapper.find('input[type="email"]').setValue('a@b.com');
    await wrapper.find('input[type="password"]').setValue('bad');
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();
    expect(wrapper.text()).toContain('Invalid email or password.');
  });

  test('403 shows activation message', async () => {
    api.login.mockRejectedValue({ response: { status: 403 } });
    const { wrapper } = await mountView();
    await wrapper.find('input[type="email"]').setValue('a@b.com');
    await wrapper.find('input[type="password"]').setValue('pw');
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();
    expect(wrapper.text()).toContain('Account not yet activated. Please check your email.');
  });

  test('success pushes router to /dashboard', async () => {
    api.login.mockResolvedValue({
      data: { access_token: 't0ken', user: { profile: { firstname: 'Marc' } } },
    });
    const { wrapper, router } = await mountView();
    const push = jest.spyOn(router, 'push');
    await wrapper.find('input[type="email"]').setValue('a@b.com');
    await wrapper.find('input[type="password"]').setValue('pw');
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();
    expect(api.login).toHaveBeenCalledWith('a@b.com', 'pw');
    expect(push).toHaveBeenCalledWith('/dashboard');
  });
});
