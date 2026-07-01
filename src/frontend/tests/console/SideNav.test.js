import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import router from '../../src/apps/console/router/index.js';
import SideNav from '../../src/apps/console/components/SideNav.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

// PRI-37: project detail/edit live at /customers/:customerId/projects/... for resource
// nesting, but they belong to the "Projects" section of the sidebar, not "Customers".
// The active nav item is driven by the resolved route's `navSection` meta rather than
// a raw URL-path prefix, so these regression checks navigate the *real* router config.

function activeLabels(wrapper) {
  return wrapper
    .findAll('a')
    .filter((a) => a.classes().includes('text-accent'))
    .map((a) => a.text());
}

async function mountAt(path) {
  router.push(path);
  await router.isReady();
  const wrapper = mount(SideNav, { global: { plugins: [router] } });
  await flushPromises();
  return wrapper;
}

beforeEach(async () => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  localStorage.clear();
  localStorage.setItem('rylees_token', 'tok');
  api.getMe.mockResolvedValue({ data: { id: 'u1', profile: { firstname: 'Marc' } } });
  api.getAllProjects.mockResolvedValue({ data: { data: [] } });
});

describe('SideNav active state', () => {
  test('highlights Projects on the global projects overview', async () => {
    const wrapper = await mountAt('/projects');
    expect(activeLabels(wrapper)).toEqual(['Projects']);
  });

  test('highlights Projects (not Customers) on a project detail page', async () => {
    const wrapper = await mountAt('/customers/c1/projects/p1');
    expect(router.currentRoute.value.name).toBe('project-detail');
    expect(activeLabels(wrapper)).toEqual(['Projects']);
  });

  test('highlights Projects (not Customers) on a project edit page', async () => {
    const wrapper = await mountAt('/customers/c1/projects/p1/edit');
    expect(router.currentRoute.value.name).toBe('project-edit');
    expect(activeLabels(wrapper)).toEqual(['Projects']);
  });

  test('highlights Customers on a customer detail page', async () => {
    const wrapper = await mountAt('/customers/c1');
    expect(router.currentRoute.value.name).toBe('customer-detail');
    expect(activeLabels(wrapper)).toEqual(['Customers']);
  });

  test('highlights Dashboard on the dashboard', async () => {
    const wrapper = await mountAt('/dashboard');
    expect(activeLabels(wrapper)).toEqual(['Dashboard']);
  });
});
