import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import AccountView from '../../src/apps/console/views/AccountView.vue';
import { useAuthStore } from '../../src/apps/console/stores/auth.js';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const ConsoleLayoutStub = {
  name: 'ConsoleLayout',
  template: '<div><slot /><slot name="sidebar" /></div>',
};

async function mountView() {
  const auth = useAuthStore();
  auth.user = {
    api_key: 'ryl_key',
    profile: { firstname: 'Marc', lastname: 'Z' },
    organisation: { name: 'Acme', city: 'Zurich' },
  };
  const wrapper = mount(AccountView, {
    global: { stubs: { ConsoleLayout: ConsoleLayoutStub } },
  });
  await flushPromises();
  return { wrapper };
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
});

describe('AccountView', () => {
  test('client-side mismatch of new/confirm password blocks the API call', async () => {
    const { wrapper } = await mountView();
    const inputs = wrapper.findAll('input[type="password"]');
    // order: current_password, new_password, confirm_new_password
    await inputs[1].setValue('newpass1');
    await inputs[2].setValue('different');
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();
    expect(api.updateMe).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Passwords do not match');
  });

  test('422 current_password error shows an inline error on that field', async () => {
    api.updateMe.mockRejectedValue({
      response: {
        status: 422,
        data: { errors: { current_password: ['wrong'] } },
      },
    });
    const { wrapper } = await mountView();
    const inputs = wrapper.findAll('input[type="password"]');
    await inputs[0].setValue('oldpass');
    await inputs[1].setValue('newpass1');
    await inputs[2].setValue('newpass1');
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();
    expect(api.updateMe).toHaveBeenCalled();
    expect(wrapper.text()).toContain('Current password is incorrect.');
  });
});
