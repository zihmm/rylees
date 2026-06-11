import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import CustomersView from '../../src/apps/console/views/CustomersView.vue';
import { useCustomersStore } from '../../src/apps/console/stores/customers.js';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div />' };

const ConsoleLayoutStub = {
  name: 'ConsoleLayout',
  template: '<div><slot /><slot name="sidebar" /></div>',
};

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/customers', name: 'customers', component: CustomersView },
      { path: '/customers/new', name: 'customer-create', component: Blank },
      { path: '/customers/:id', name: 'customer-detail', component: Blank },
    ],
  });
}

function pageFixture(page) {
  return {
    data: {
      data: [
        {
          id: 'c1',
          organisation: { name: 'Acme Ltd', city: 'Zurich' },
          industry: { name: 'Manufacturing' },
          description: 'A great company',
          projects_count: 4,
          updated_at: '2026-06-01T10:00:00Z',
        },
      ],
      meta: { current_page: page, last_page: 3, total: 5 },
    },
  };
}

async function mountView() {
  const router = makeRouter();
  router.push('/customers');
  await router.isReady();
  const wrapper = mount(CustomersView, {
    global: { plugins: [router], stubs: { ConsoleLayout: ConsoleLayoutStub } },
  });
  await flushPromises();
  return { wrapper, router };
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  api.getCustomers.mockResolvedValue(pageFixture(1));
});

describe('CustomersView', () => {
  test('renders org card with name, industry and description', async () => {
    const { wrapper } = await mountView();
    const text = wrapper.text();
    expect(text).toContain('Acme Ltd');
    expect(text).toContain('Manufacturing');
    expect(text).toContain('A great company');
  });

  test('card links to the customer detail route', async () => {
    const { wrapper } = await mountView();
    const links = wrapper.findAll('a').map((a) => a.attributes('href'));
    expect(links.some((h) => h && h.includes('/customers/c1'))).toBe(true);
  });

  test('"New Organisation" action navigates to the create route', async () => {
    const { wrapper, router } = await mountView();
    const push = jest.spyOn(router, 'push');
    const btn = wrapper
      .findAll('button')
      .find((b) => b.text().includes('New Organisation'));
    expect(btn).toBeTruthy();
    await btn.trigger('click');
    expect(push).toHaveBeenCalledWith('/customers/new');
  });

  test('pagination Next calls fetchCustomers with the new page', async () => {
    const { wrapper } = await mountView();
    const store = useCustomersStore();
    const spy = jest.spyOn(store, 'fetchCustomers');
    const next = wrapper
      .findAll('button')
      .find((b) => b.text() === 'Next');
    expect(next).toBeTruthy();
    await next.trigger('click');
    expect(spy).toHaveBeenCalledWith(2, 20);
  });
});
