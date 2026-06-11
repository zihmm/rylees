import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import ProjectDetailView from '../../src/apps/console/views/ProjectDetailView.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div />' };

const ConsoleLayoutStub = {
  name: 'ConsoleLayout',
  template: '<div><slot /><slot name="sidebar" /></div>',
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

const releaseHistory = {
  items: [
    {
      id: 'r1',
      version: '3.5.0',
      body: 'Latest release notes body with enough detail to verify the summary column truncates after the first one hundred characters as required by the spec.',
      publishedAt: '2026-06-01T10:00:00Z',
    },
  ],
};

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/customers/:customerId/projects/:id', name: 'project-detail', component: ProjectDetailView },
      { path: '/customers/:customerId/projects/:id/edit', name: 'project-edit', component: Blank },
    ],
  });
}

async function mountView() {
  const router = makeRouter();
  router.push('/customers/c1/projects/p1');
  await router.isReady();
  const wrapper = mount(ProjectDetailView, {
    global: { plugins: [router], stubs: { ConsoleLayout: ConsoleLayoutStub } },
  });
  await flushPromises();
  return { wrapper };
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
  api.getProject.mockResolvedValue({ data: project });
  api.getReleaseHistory.mockResolvedValue({ data: releaseHistory });
});

describe('ProjectDetailView', () => {
  test('shows name, description and LLM settings', async () => {
    const { wrapper } = await mountView();
    const text = wrapper.text();
    expect(text).toContain('Member Portal');
    expect(text).toContain('The customer portal project.');
    expect(text).toContain('Professional');
    expect(text).toContain('0.7');
  });

  test('renders the token inside a <code> block', async () => {
    const { wrapper } = await mountView();
    const code = wrapper.find('code');
    expect(code.exists()).toBe(true);
    expect(code.text()).toBe('ryl_secret_token_123');
  });

  test('copy button writes the token to the clipboard', async () => {
    const writeText = jest.fn();
    Object.assign(navigator, { clipboard: { writeText } });
    const { wrapper } = await mountView();
    // The copy button sits next to the <code> token.
    const btn = wrapper.find('button[aria-label="Copy to clipboard"]');
    expect(btn.exists()).toBe(true);
    await btn.trigger('click');
    expect(writeText).toHaveBeenCalledWith('ryl_secret_token_123');
  });

  test('release notes render version, absolute date and body in the sidebar', async () => {
    const { wrapper } = await mountView();
    const text = wrapper.text();
    expect(text).toContain('Latest release notes body with enough detail');
    expect(text).toContain('v3.5.0');
    expect(text).toContain('01. Juni 2026');
  });

  test('release history table renders the first 100 body characters', async () => {
    const { wrapper } = await mountView();
    const tableText = wrapper.find('table').text();
    expect(tableText).toContain('Version');
    expect(tableText).toContain('Published');
    expect(tableText).toContain('Summary');
    expect(tableText).toContain(releaseHistory.items[0].body.slice(0, 100) + '...');
  });
});
