import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import CustomerDetailView from '../../src/apps/console/views/CustomerDetailView.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div />' };

const ConsoleLayoutStub = {
  name: 'ConsoleLayout',
  template: '<div><slot /><slot name="sidebar" /><slot name="footer-actions" /></div>',
};

const customer = {
  id: 'c1',
  organisation: {
    name: 'Acme Ltd',
    slug: 'acme-ltd',
    city: 'Zurich',
    website: 'https://acme.test',
    email: 'hi@acme.test',
  },
  industry: { name: 'Manufacturing' },
  main_contact: { id: 'k1' },
  contacts: [
    { id: 'k1', firstname: 'Ada', lastname: 'Lovelace', email: 'ada@acme.test' },
    { id: 'k2', firstname: 'Alan', lastname: 'Turing', email: 'alan@acme.test' },
  ],
  projects: [
    { id: 'p1', name: 'Member Portal', key: 'member-portal' },
    { id: 'p2', name: 'Billing API', key: 'billing-api' },
  ],
};

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/customers', name: 'customers', component: Blank },
      { path: '/customers/:id', name: 'customer-detail', component: CustomerDetailView },
      { path: '/customers/:id/edit', name: 'customer-edit', component: Blank },
      { path: '/customers/:customerId/projects/new', name: 'project-create', component: Blank },
      { path: '/customers/:customerId/projects/:id', name: 'project-detail', component: Blank },
      { path: '/customers/:customerId/projects/:id/edit', name: 'project-edit', component: Blank },
    ],
  });
}

async function mountView() {
  const router = makeRouter();
  router.push('/customers/c1');
  await router.isReady();
  const wrapper = mount(CustomerDetailView, {
    global: { plugins: [router], stubs: { ConsoleLayout: ConsoleLayoutStub } },
  });
  await flushPromises();
  return { wrapper, router };
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  api.getCustomer.mockResolvedValue({ data: customer });
});

describe('CustomerDetailView', () => {
  test('calls getCustomer with the route id', async () => {
    await mountView();
    expect(api.getCustomer).toHaveBeenCalledWith('c1');
  });

  test('renders org info', async () => {
    const { wrapper } = await mountView();
    const text = wrapper.text();
    expect(text).toContain('Acme Ltd');
    expect(text).toContain('Manufacturing');
    expect(text).toContain('Zurich');
  });

  test('renders the contacts list', async () => {
    const { wrapper } = await mountView();
    const text = wrapper.text();
    expect(text).toContain('Ada Lovelace');
    expect(text).toContain('ada@acme.test');
    expect(text).toContain('Alan Turing');
  });

  test('renders projects with links to the project detail route', async () => {
    const { wrapper } = await mountView();
    expect(wrapper.text()).toContain('Member Portal');
    const links = wrapper.findAll('a').map((a) => a.attributes('href'));
    expect(links.some((h) => h && h.includes('/customers/c1/projects/p1'))).toBe(true);
    expect(links.some((h) => h && h.includes('/customers/c1/projects/p2'))).toBe(true);
  });

  test('renders a Delete customer button styled as danger', async () => {
    const { wrapper } = await mountView();
    const button = wrapper.findAll('button').find((b) => b.text() === 'Delete customer');
    expect(button).toBeTruthy();
    expect(button.classes()).toContain('bg-danger');
  });

  test('clicking Delete customer shows a warning confirmation dialog without calling the API', async () => {
    const { wrapper } = await mountView();

    expect(wrapper.text()).not.toContain('Delete this customer?');

    await wrapper.findAll('button').find((b) => b.text() === 'Delete customer').trigger('click');

    expect(wrapper.text()).toContain('Delete this customer?');
    expect(api.deleteCustomer).not.toHaveBeenCalled();
  });

  test('cancelling the confirmation dialog does not delete the customer', async () => {
    const { wrapper } = await mountView();

    await wrapper.findAll('button').find((b) => b.text() === 'Delete customer').trigger('click');
    await wrapper.findAll('button').find((b) => b.text() === 'Cancel').trigger('click');

    expect(wrapper.text()).not.toContain('Delete this customer?');
    expect(api.deleteCustomer).not.toHaveBeenCalled();
  });

  test('confirming deletion calls the API and redirects to the customers list', async () => {
    api.deleteCustomer.mockResolvedValue({});
    const { wrapper, router } = await mountView();
    const pushSpy = jest.spyOn(router, 'push');

    await wrapper.findAll('button').find((b) => b.text() === 'Delete customer').trigger('click');
    // Scope to the confirm dialog itself: a contact row also has a "Delete" button.
    const dialog = wrapper.find('.fixed.inset-0');
    await dialog.findAll('button').find((b) => b.text() === 'Delete').trigger('click');
    await flushPromises();

    expect(api.deleteCustomer).toHaveBeenCalledWith('c1');
    expect(pushSpy).toHaveBeenCalledWith('/customers');
  });

  test('shows an error notification and keeps the dialog closed when deletion fails', async () => {
    api.deleteCustomer.mockRejectedValue({ response: { data: { message: 'Nope' } } });
    const { wrapper } = await mountView();

    await wrapper.findAll('button').find((b) => b.text() === 'Delete customer').trigger('click');
    const dialog = wrapper.find('.fixed.inset-0');
    await dialog.findAll('button').find((b) => b.text() === 'Delete').trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('Could not delete customer');
    expect(wrapper.text()).not.toContain('Delete this customer?');
  });
});
