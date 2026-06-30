import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import ProjectsView from '../../src/apps/console/views/ProjectsView.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div />' };

const ConsoleLayoutStub = {
  name: 'ConsoleLayout',
  template: '<div><slot name="header-actions" /><slot /><slot name="sidebar" /></div>',
};

const projects = [
  {
    id: 'p1',
    name: 'Member Portal',
    key: 'member-portal',
    customer_id: 'c1',
    customer_name: 'Acme Ltd',
    organisation_slug: 'acme-ltd',
    description: 'The short description that should be replaced by the link.',
    version: '1.2.3',
    updated_at: '2026-06-01T10:00:00Z',
  },
];

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/projects', name: 'projects', component: ProjectsView },
      { path: '/projects/new', name: 'project-create-global', component: Blank },
      { path: '/customers/:customerId/projects/:id/edit', name: 'project-edit', component: Blank },
      { path: '/customers/:id/edit', name: 'customer-edit', component: Blank },
    ],
  });
}

async function mountView() {
  const router = makeRouter();
  router.push('/projects');
  await router.isReady();
  const wrapper = mount(ProjectsView, {
    global: { plugins: [router], stubs: { ConsoleLayout: ConsoleLayoutStub } },
  });
  await flushPromises();
  return { wrapper };
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  api.getAllProjects.mockResolvedValue({ data: { data: projects } });
});

describe('ProjectsView', () => {
  test('shows the release-history domain link instead of the description', async () => {
    const { wrapper } = await mountView();
    // The card itself is a RouterLink; the history link is the inner new-tab anchor.
    const link = wrapper.find('a[target="_blank"]');
    expect(link.exists()).toBe(true);
    // Displayed without a scheme, but the href is the full https URL opened in a new tab.
    expect(link.text()).toContain('acme-ltd.rylees.ai/member-portal');
    expect(link.attributes('href')).toBe('https://acme-ltd.rylees.ai/member-portal');
    expect(link.attributes('target')).toBe('_blank');
    expect(wrapper.text()).not.toContain('The short description that should be replaced');
  });
});
