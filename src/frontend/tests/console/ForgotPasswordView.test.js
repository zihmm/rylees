import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import ForgotPasswordView from '../../src/apps/console/views/ForgotPasswordView.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div><slot /></div>' };

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView },
      { path: '/login', name: 'login', component: Blank },
    ],
  });
}

async function mountView() {
  const router = makeRouter();
  router.push('/forgot-password');
  await router.isReady();
  const wrapper = mount(ForgotPasswordView, { global: { plugins: [router] } });
  await flushPromises();
  return { wrapper };
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
});

describe('ForgotPasswordView', () => {
  test('renders an email input', async () => {
    const { wrapper } = await mountView();
    expect(wrapper.find('input[type="email"]').exists()).toBe(true);
  });

  test('submitting calls forgotPassword and shows the non-enumerating success message', async () => {
    api.forgotPassword.mockResolvedValue({ data: {} });
    const { wrapper } = await mountView();
    await wrapper.find('input[type="email"]').setValue('a@b.com');
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();
    expect(api.forgotPassword).toHaveBeenCalledWith('a@b.com');
    expect(wrapper.text()).toContain('If an account exists for this email, a password reset link has been sent.');
  });

  test('422 surfaces a field error and does not show success', async () => {
    api.forgotPassword.mockRejectedValue({
      response: { status: 422, data: { errors: { username: ['The username must be a valid email address.'] } } },
    });
    const { wrapper } = await mountView();
    await wrapper.find('input[type="email"]').setValue('not-an-email');
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();
    expect(wrapper.text()).toContain('The username must be a valid email address.');
    expect(wrapper.text()).not.toContain('a password reset link has been sent');
  });
});
