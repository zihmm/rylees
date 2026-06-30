import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import ReleaseHistoryView from '../../src/apps/history/views/ReleaseHistoryView.vue';
import NotFoundView from '../../src/apps/history/views/NotFoundView.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const SLUG = 'acme-ltd';
const KEY = 'member-portal';

const fixture = {
  project: { id: 'p1', name: 'Member Portal', key: KEY, language: 'de' },
  items: [
    { id: 'i1', version: '3.5.0', body: 'Latest release with <b>bold</b> text.', publishedAt: '2026-06-01T10:00:00Z' },
    { id: 'i2', version: '3.4.1', body: 'A 3.x patch.', publishedAt: '2026-05-01T10:00:00Z' },
    { id: 'i3', version: '2.0.0', body: 'Major 2 release.', publishedAt: '2025-12-01T10:00:00Z' },
    { id: 'i4', version: '1.0.0', body: 'First release.', publishedAt: '2025-01-01T10:00:00Z' },
  ],
};

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/:projectKey', name: 'release-history', component: ReleaseHistoryView },
      { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
    ],
  });
}

async function mountView({ router } = {}) {
  const r = router || makeRouter();
  r.push('/' + KEY);
  await r.isReady();
  const wrapper = mount(ReleaseHistoryView, {
    global: {
      plugins: [r],
      provide: { customerSlug: SLUG, projectKey: KEY },
    },
  });
  await flushPromises();
  return { wrapper, router: r };
}

beforeEach(() => {
  jest.clearAllMocks();
  api.getReleaseHistory.mockResolvedValue({ data: JSON.parse(JSON.stringify(fixture)) });
  document.title = '';
});

describe('ReleaseHistoryView', () => {
  test('extracts customerSlug and projectKey from injected values', async () => {
    await mountView();
    expect(api.getReleaseHistory).toHaveBeenCalledWith(SLUG, KEY);
  });

  test('calls getReleaseHistory on mount', async () => {
    await mountView();
    expect(api.getReleaseHistory).toHaveBeenCalledTimes(1);
  });

  test('sets document.title to project name', async () => {
    await mountView();
    expect(document.title).toBe('Member Portal — Release History');
  });

  test('renders timeline entries with version badge and relative date', async () => {
    const { wrapper } = await mountView();
    const text = wrapper.text();
    expect(text).toContain('v3.5.0');
    expect(text).toContain('v3.4.1');
    // Relative dates are localized to the project's configured language
    // (here 'de' → German "Vor …", first letter capitalized) per DESIGN-SPEC-RH §8.1.
    expect(text).toMatch(/Vor\s/);
  });

  test('relative date label follows the project language setting', async () => {
    api.getReleaseHistory.mockResolvedValue({
      data: { ...JSON.parse(JSON.stringify(fixture)), project: { id: 'p1', name: 'Member Portal', key: KEY, language: 'en' } },
    });
    const { wrapper } = await mountView();
    const text = wrapper.text();
    expect(text).toMatch(/ago/);
    expect(text).not.toMatch(/vor\s/i);
  });

  test('language switcher UI is deferred (not rendered)', async () => {
    // Per DESIGN-SPEC-RH §8 deviation 2 the DE/EN/FR switcher is deferred;
    // switchLanguage stays wired (exposed) but no switcher buttons are shown.
    const { wrapper } = await mountView();
    const buttons = wrapper.findAll('button').map((button) => button.text().toLowerCase());
    expect(buttons).not.toContain('en');
    expect(buttons).not.toContain('fr');
    expect(typeof wrapper.vm.switchLanguage).toBe('function');
  });

  test('on 404 renders NotFoundView', async () => {
    api.getReleaseHistory.mockRejectedValue({ response: { status: 404 } });
    const { router } = await mountView();
    await flushPromises();
    expect(router.currentRoute.value.name).toBe('not-found');
  });

  test('switching to EN calls translate endpoint and replaces bodies', async () => {
    api.translateReleaseHistory.mockResolvedValue({
      data: {
        language: 'en',
        items: [
          { id: 'i1', version: '3.5.0', body: 'Translated latest.' },
          { id: 'i2', version: '3.4.1', body: 'Translated patch.' },
        ],
      },
    });
    const { wrapper } = await mountView();
    await wrapper.vm.switchLanguage('en');
    await flushPromises();
    expect(api.translateReleaseHistory).toHaveBeenCalledWith(SLUG, KEY, 'en');
    const text = wrapper.text();
    expect(text).toContain('Translated latest.');
    expect(text).toContain('Translated patch.');
    // Version preserved from the original items.
    expect(text).toContain('v3.5.0');
  });

  test('switching back to DE restores original bodies without API call', async () => {
    api.translateReleaseHistory.mockResolvedValue({
      data: { language: 'en', items: [{ id: 'i1', version: '3.5.0', body: 'Translated latest.' }] },
    });
    const { wrapper } = await mountView();
    await wrapper.vm.switchLanguage('en');
    await flushPromises();
    api.translateReleaseHistory.mockClear();

    await wrapper.vm.switchLanguage('de');
    await flushPromises();
    expect(api.translateReleaseHistory).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Latest release with <b>bold</b> text.');
  });

  test('shows loading skeleton while translate request is in flight', async () => {
    let resolveTranslate;
    api.translateReleaseHistory.mockReturnValue(
      new Promise((resolve) => {
        resolveTranslate = resolve;
      })
    );
    const { wrapper } = await mountView();
    const pending = wrapper.vm.switchLanguage('en');
    await flushPromises();
    expect(wrapper.find('.animate-pulse').exists()).toBe(true);

    resolveTranslate({ data: { language: 'en', items: [] } });
    await pending;
    await flushPromises();
    expect(wrapper.find('.animate-pulse').exists()).toBe(false);
  });

  test('paginates release notes 4 per page with disabled edge arrows', async () => {
    const items = Array.from({ length: 5 }, (_, i) => ({
      id: `p${i}`,
      version: `1.0.${i}`,
      body: `Body number ${i}.`,
      publishedAt: '2026-06-01T10:00:00Z',
    }));
    api.getReleaseHistory.mockResolvedValue({
      data: { project: { id: 'p1', name: 'Member Portal', key: KEY, language: 'de' }, items },
    });
    const { wrapper } = await mountView();

    const prev = wrapper.get('[aria-label="Newer releases"]');
    const next = wrapper.get('[aria-label="Older releases"]');

    // Page 1: first four entries; prev disabled, next enabled.
    expect(wrapper.text()).toContain('Body number 0.');
    expect(wrapper.text()).toContain('Body number 3.');
    expect(wrapper.text()).not.toContain('Body number 4.');
    expect(prev.attributes('disabled')).toBeDefined();
    expect(next.attributes('disabled')).toBeUndefined();

    await next.trigger('click');

    // Page 2: the remaining entry; prev enabled, next disabled.
    expect(wrapper.text()).toContain('Body number 4.');
    expect(wrapper.text()).not.toContain('Body number 0.');
    expect(prev.attributes('disabled')).toBeUndefined();
    expect(next.attributes('disabled')).toBeDefined();
  });

  test('body text rendered with text interpolation, not v-html', async () => {
    const { wrapper } = await mountView();
    // The body contains literal "<b>bold</b>"; with interpolation it appears as
    // escaped text and creates NO real <b> element.
    expect(wrapper.find('b').exists()).toBe(false);
    expect(wrapper.text()).toContain('<b>bold</b>');
  });
});
