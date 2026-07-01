import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import ProjectEditView from '../../src/apps/console/views/ProjectEditView.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div />' };

const ConsoleLayoutStub = {
  name: 'ConsoleLayout',
  template: '<div><slot /><slot name="sidebar" /><slot name="footer-actions" /></div>',
};

const project = {
  id: 'p1',
  name: 'Member Portal',
  key: 'member-portal',
  description: 'The customer portal project.',
  token: 'ryl_secret_token_123',
  llm: { tonality: 'Professional', temperature: 0.7 },
  customer: { organisation_slug: 'acme-ltd' },
};

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/projects', name: 'projects', component: Blank },
      { path: '/customers/:customerId/projects/:id/edit', name: 'project-edit', component: ProjectEditView },
    ],
  });
}

async function mountView() {
  const router = makeRouter();
  router.push('/customers/c1/projects/p1/edit');
  await router.isReady();
  const wrapper = mount(ProjectEditView, {
    global: { plugins: [router], stubs: { ConsoleLayout: ConsoleLayoutStub } },
  });
  await flushPromises();
  return { wrapper, router };
}

function findButton(wrapper, label) {
  return wrapper.findAll('button').find((b) => b.text().includes(label));
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  api.getLlmTonalities.mockResolvedValue({ data: { items: [{ id: 't1', name: 'Professional' }] } });
  api.getLlmTemperatures.mockResolvedValue({ data: { items: [{ id: 'tmp1', name: 'Balanced', value: 0.7 }] } });
  api.getProject.mockResolvedValue({ data: project });
  api.getReleaseHistory.mockResolvedValue({ data: { items: [] } });
  api.deleteProject.mockResolvedValue({});
  window.confirm = jest.fn();
});

describe('ProjectEditView — delete project', () => {
  test('renders a Delete button positioned between Cancel and Save', async () => {
    const { wrapper } = await mountView();
    const buttons = wrapper.findAll('button').map((b) => b.text());
    const cancelIndex = buttons.findIndex((t) => t.includes('Cancel'));
    const deleteIndex = buttons.findIndex((t) => t.includes('Delete'));
    const saveIndex = buttons.findIndex((t) => t.includes('Save'));

    expect(cancelIndex).toBeGreaterThanOrEqual(0);
    expect(deleteIndex).toBeGreaterThan(cancelIndex);
    expect(saveIndex).toBeGreaterThan(deleteIndex);
  });

  test('does nothing when the confirmation is declined', async () => {
    window.confirm.mockReturnValue(false);
    const { wrapper } = await mountView();

    await findButton(wrapper, 'Delete').trigger('click');
    await flushPromises();

    expect(window.confirm).toHaveBeenCalled();
    expect(api.deleteProject).not.toHaveBeenCalled();
  });

  test('deletes the project and navigates to /projects when confirmed', async () => {
    window.confirm.mockReturnValue(true);
    const { wrapper, router } = await mountView();

    await findButton(wrapper, 'Delete').trigger('click');
    await flushPromises();

    expect(api.deleteProject).toHaveBeenCalledWith('c1', 'p1');
    expect(router.currentRoute.value.path).toBe('/projects');
  });
});
