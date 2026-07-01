import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import SideNav from '../../src/apps/console/components/SideNav.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div />' };

const projects = [
  { id: 'p1', name: 'Member Portal', customer_id: 'c1' },
  { id: 'p2', name: 'Loyalty App', customer_id: 'c2' },
];

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/dashboard', name: 'dashboard', component: Blank },
      { path: '/customers', name: 'customers', component: Blank },
      { path: '/projects', name: 'projects', component: Blank },
      { path: '/customers/:customerId/projects/:id', name: 'project-detail', component: Blank },
    ],
  });
}

async function mountNav() {
  const router = makeRouter();
  router.push('/dashboard');
  await router.isReady();
  const wrapper = mount(SideNav, { global: { plugins: [router] } });
  await flushPromises();
  return { wrapper, router };
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  api.getAllProjects.mockResolvedValue({ data: { data: projects } });
});

describe('SideNav', () => {
  test('renders each current project as a link to its project detail page', async () => {
    const { wrapper } = await mountNav();

    const projectLinks = wrapper.findAll('a').filter((a) => a.text().includes('Member Portal') || a.text().includes('Loyalty App'));
    expect(projectLinks).toHaveLength(2);

    const memberPortalLink = projectLinks.find((a) => a.text().includes('Member Portal'));
    expect(memberPortalLink.attributes('href')).toBe('/customers/c1/projects/p1');

    const loyaltyLink = projectLinks.find((a) => a.text().includes('Loyalty App'));
    expect(loyaltyLink.attributes('href')).toBe('/customers/c2/projects/p2');
  });

  test('navigates to the project detail route when a current project is clicked', async () => {
    const { wrapper, router } = await mountNav();

    const link = wrapper.findAll('a').find((a) => a.text().includes('Member Portal'));
    await link.trigger('click');
    await flushPromises();

    expect(router.currentRoute.value.name).toBe('project-detail');
    expect(router.currentRoute.value.params).toEqual({ customerId: 'c1', id: 'p1' });
  });
});
