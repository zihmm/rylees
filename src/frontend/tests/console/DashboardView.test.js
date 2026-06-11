import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import DashboardView from '../../src/apps/console/views/DashboardView.vue';
import { useAuthStore } from '../../src/apps/console/stores/auth.js';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

// Render both slots, avoid SideNav/router side effects from the real layout.
const ConsoleLayoutStub = {
  name: 'ConsoleLayout',
  template: '<div><slot /><slot name="sidebar" /></div>',
};

async function mountView() {
  const wrapper = mount(DashboardView, {
    global: { stubs: { ConsoleLayout: ConsoleLayoutStub } },
  });
  await flushPromises();
  return { wrapper };
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  api.getCustomers.mockResolvedValue({
    data: {
      data: [
        { id: 'c1', projects_count: 3 },
        { id: 'c2', projects_count: 2 },
      ],
      meta: { current_page: 1, last_page: 1, total: 7 },
    },
  });
});

describe('DashboardView', () => {
  test('shows the firstname in the heading', async () => {
    const auth = useAuthStore();
    auth.user = { profile: { firstname: 'Marc' } };
    const { wrapper } = await mountView();
    expect(wrapper.find('h1').text()).toContain('Marc');
  });

  test('shows organisations total from meta.total', async () => {
    const { wrapper } = await mountView();
    expect(wrapper.text()).toContain('7');
  });

  test('sums projects_count across customers', async () => {
    const { wrapper } = await mountView();
    // 3 + 2 = 5
    expect(wrapper.text()).toContain('5');
  });
});
