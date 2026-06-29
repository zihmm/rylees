import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import ProjectCreateView from '../../src/apps/console/views/ProjectCreateView.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div />' };

const ConsoleLayoutStub = {
  name: 'ConsoleLayout',
  template: '<div><slot /><slot name="footer-actions" /></div>',
};

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/projects', name: 'projects', component: Blank },
      { path: '/projects/new', name: 'project-create-global', component: ProjectCreateView },
      { path: '/customers', name: 'customers', component: Blank },
      { path: '/customers/:customerId/projects/new', name: 'project-create', component: ProjectCreateView },
      { path: '/customers/:customerId', name: 'customer-detail', component: Blank },
      { path: '/customers/:customerId/projects/:id', name: 'project-detail', component: Blank },
    ],
  });
}

async function mountAt(path) {
  const router = makeRouter();
  router.push(path);
  await router.isReady();
  const wrapper = mount(ProjectCreateView, {
    global: { plugins: [router], stubs: { ConsoleLayout: ConsoleLayoutStub } },
  });
  await flushPromises();
  return { wrapper, router };
}

function saveButton(wrapper) {
  return wrapper.findAll('button').find((b) => b.text().includes('Save'));
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  api.getLlmTonalities.mockResolvedValue({ data: { items: [{ id: 't1', name: 'Friendly' }] } });
  api.getLlmTemperatures.mockResolvedValue({ data: { items: [{ id: 'tmp1', name: 'Low', value: 0.2 }] } });
  api.getCustomers.mockResolvedValue({
    data: {
      data: [
        { id: 'c1', organisation: { name: 'Acme Ltd' } },
        { id: 'c2', organisation: { name: 'Globex' } },
      ],
    },
  });
  api.createProject.mockResolvedValue({ data: { id: 'p9' } });
});

describe('ProjectCreateView — global flow (/projects/new)', () => {
  test('loads the customer list for the dropdown', async () => {
    await mountAt('/projects/new');
    expect(api.getCustomers).toHaveBeenCalledWith(1, 100);
  });

  test('renders a customer dropdown as the first field with all customers', async () => {
    const { wrapper } = await mountAt('/projects/new');
    const selects = wrapper.findAll('select');
    // customer, language, tonality, temperature
    expect(selects).toHaveLength(4);
    expect(selects[0].text()).toContain('Acme Ltd');
    expect(selects[0].text()).toContain('Globex');
  });

  test('saves the project against the selected customer and redirects to /projects', async () => {
    const { wrapper, router } = await mountAt('/projects/new');
    const selects = wrapper.findAll('select');
    await selects[0].setValue('c1'); // customer
    await wrapper.find('input').setValue('Member Portal'); // name
    await selects[2].setValue('t1'); // tonality
    await selects[3].setValue('tmp1'); // temperature

    await saveButton(wrapper).trigger('click');
    await flushPromises();

    expect(api.createProject).toHaveBeenCalledWith('c1', {
      name: 'Member Portal',
      description: '',
      language: 'en',
      llm_tonality_id: 't1',
      llm_temperature_id: 'tmp1',
    });
    expect(router.currentRoute.value.path).toBe('/projects');
  });

  test('blocks save and shows an error when no customer is selected', async () => {
    const { wrapper } = await mountAt('/projects/new');
    await wrapper.find('input').setValue('Member Portal');

    await saveButton(wrapper).trigger('click');
    await flushPromises();

    expect(api.createProject).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Please select a customer.');
  });
});

describe('ProjectCreateView — customer flow (/customers/:customerId/projects/new)', () => {
  test('does not fetch customers or render the customer dropdown', async () => {
    const { wrapper } = await mountAt('/customers/c1/projects/new');
    expect(api.getCustomers).not.toHaveBeenCalled();
    // language, tonality, temperature — no customer select.
    expect(wrapper.findAll('select')).toHaveLength(3);
  });

  test('saves against the route customer and redirects to the new project', async () => {
    const { wrapper, router } = await mountAt('/customers/c1/projects/new');
    await wrapper.find('input').setValue('Billing API');
    const selects = wrapper.findAll('select');
    await selects[1].setValue('t1'); // tonality
    await selects[2].setValue('tmp1'); // temperature

    await saveButton(wrapper).trigger('click');
    await flushPromises();

    expect(api.createProject).toHaveBeenCalledWith('c1', {
      name: 'Billing API',
      description: '',
      language: 'en',
      llm_tonality_id: 't1',
      llm_temperature_id: 'tmp1',
    });
    expect(router.currentRoute.value.path).toBe('/customers/c1/projects/p9');
  });
});
