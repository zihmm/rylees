import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import RegisterView from '../../src/apps/console/views/RegisterView.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div><slot /></div>' };

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/register', name: 'register', component: RegisterView },
      { path: '/login', name: 'login', component: Blank },
    ],
  });
}

async function mountView() {
  const router = makeRouter();
  router.push('/register');
  await router.isReady();
  const wrapper = mount(RegisterView, { global: { plugins: [router] } });
  await flushPromises();
  return { wrapper };
}

// Fill required fields so client-side password match passes.
async function fillValidForm(wrapper) {
  const inputs = wrapper.findAll('input');
  // [firstname, lastname, email, password, confirm_password, org_name, ...]
  await inputs[3].setValue('secret');
  await inputs[4].setValue('secret');
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  localStorage.clear();
});

describe('RegisterView', () => {
  test('201 shows success message and hides the form', async () => {
    api.register.mockResolvedValue({ data: {} });
    const { wrapper } = await mountView();
    await fillValidForm(wrapper);
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();
    expect(wrapper.text()).toContain(
      'Account created. Please check your email to activate your account.'
    );
    // The form is replaced by the success block.
    expect(wrapper.find('form').exists()).toBe(false);
  });

  test('422 shows inline field errors keyed by dot-notation', async () => {
    api.register.mockRejectedValue({
      response: {
        status: 422,
        data: {
          errors: {
            'organisation.name': ['The organisation name is required.'],
            'profile.firstname': 'Firstname is required.',
          },
        },
      },
    });
    const { wrapper } = await mountView();
    await fillValidForm(wrapper);
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();
    // Form still visible, inline errors rendered next to fields.
    expect(wrapper.find('form').exists()).toBe(true);
    expect(wrapper.text()).toContain('The organisation name is required.');
    expect(wrapper.text()).toContain('Firstname is required.');
  });

  test('client-side password mismatch blocks the API call', async () => {
    api.register.mockResolvedValue({ data: {} });
    const { wrapper } = await mountView();
    const inputs = wrapper.findAll('input');
    await inputs[3].setValue('secret');
    await inputs[4].setValue('different');
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();
    expect(api.register).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Passwords do not match');
  });
});
