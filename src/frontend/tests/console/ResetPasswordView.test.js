import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import ResetPasswordView from '../../src/apps/console/views/ResetPasswordView.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div><slot /></div>' };

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/reset-password', name: 'reset-password', component: ResetPasswordView },
      { path: '/forgot-password', name: 'forgot-password', component: Blank },
      { path: '/login', name: 'login', component: Blank },
    ],
  });
}

async function mountView(query = '') {
  const router = makeRouter();
  router.push('/reset-password' + query);
  await router.isReady();
  const wrapper = mount(ResetPasswordView, { global: { plugins: [router] } });
  await flushPromises();
  return { wrapper };
}

async function fillAndSubmit(wrapper, password, confirm = password) {
  const inputs = wrapper.findAll('input[type="password"]');
  await inputs[0].setValue(password);
  await inputs[1].setValue(confirm);
  await wrapper.find('form').trigger('submit.prevent');
  await flushPromises();
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
});

describe('ResetPasswordView', () => {
  test('without a token, prompts to request a new link', async () => {
    const { wrapper } = await mountView();
    expect(wrapper.text()).toContain('This reset link is missing its token.');
    expect(wrapper.find('form').exists()).toBe(false);
  });

  test('rejects passwords shorter than 12 chars before calling the API', async () => {
    const { wrapper } = await mountView('?token=abc');
    await fillAndSubmit(wrapper, 'short');
    expect(api.resetPassword).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Password must be at least 12 characters.');
  });

  test('rejects mismatched passwords before calling the API', async () => {
    const { wrapper } = await mountView('?token=abc');
    await fillAndSubmit(wrapper, 'longenoughpassword', 'differentpassword');
    expect(api.resetPassword).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Passwords do not match.');
  });

  test('valid submit calls resetPassword with token and shows success', async () => {
    api.resetPassword.mockResolvedValue({ data: {} });
    const { wrapper } = await mountView('?token=abc123');
    await fillAndSubmit(wrapper, 'a-very-long-password');
    expect(api.resetPassword).toHaveBeenCalledWith('abc123', 'a-very-long-password');
    expect(wrapper.text()).toContain('Your password has been reset.');
  });

  test('404 shows an invalid/expired message', async () => {
    api.resetPassword.mockRejectedValue({ response: { status: 404 } });
    const { wrapper } = await mountView('?token=bad');
    await fillAndSubmit(wrapper, 'a-very-long-password');
    expect(wrapper.text()).toContain('This reset link is invalid or has expired. Please request a new one.');
  });
});
