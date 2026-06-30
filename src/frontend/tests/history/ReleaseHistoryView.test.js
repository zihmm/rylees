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
    { id: 'i1', version: '3.5.0', body: 'Latest release with <strong>bold</strong> text.', publishedAt: '2026-06-01T10:00:00Z' },
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
    expect(wrapper.text()).toContain('Latest release with bold text.');
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

  test('paging smoothly scrolls back to the top of the page', async () => {
    const items = Array.from({ length: 5 }, (_, i) => ({
      id: `p${i}`,
      version: `1.0.${i}`,
      body: `Body number ${i}.`,
      publishedAt: '2026-06-01T10:00:00Z',
    }));
    api.getReleaseHistory.mockResolvedValue({
      data: { project: { id: 'p1', name: 'Member Portal', key: KEY, language: 'de' }, items },
    });
    const scrollSpy = jest.spyOn(window, 'scrollTo').mockImplementation(() => {});
    const { wrapper } = await mountView();

    await wrapper.get('[aria-label="Older releases"]').trigger('click');
    expect(scrollSpy).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' });

    await wrapper.get('[aria-label="Newer releases"]').trigger('click');
    expect(scrollSpy).toHaveBeenCalledTimes(2);
    scrollSpy.mockRestore();
  });

  test('scrolling activates the lowest note whose top has crossed ~200px', async () => {
    const { wrapper } = await mountView();
    // Default fixture is a single page of four notes — one bubble per entry.
    const bubbles = () => wrapper.findAll('ol .transition-all');
    expect(bubbles()).toHaveLength(4);

    // Simulate scroll: entries 0 and 1 have crossed the 200px line, 2 and 3
    // are still below it — so entry 1 (the lowest crossed) becomes active.
    const lis = bubbles()[0].element.closest('ol').children;
    const tops = [-50, 150, 400, 700];
    Array.from(lis).forEach((li, i) => {
      li.getBoundingClientRect = () => ({ top: tops[i] });
    });
    window.dispatchEvent(new Event('scroll'));
    await new Promise((resolve) => requestAnimationFrame(() => resolve()));
    await wrapper.vm.$nextTick();

    expect(bubbles()[1].classes()).toContain('border-[3px]');
    expect(bubbles()[0].classes()).toContain('border-0');
    expect(bubbles()[2].classes()).toContain('border-0');
  });

  test('a note whose marker scrolls above the viewport hands off to the next', async () => {
    const { wrapper } = await mountView();
    const bubbles = () => wrapper.findAll('ol .transition-all');
    const lis = bubbles()[0].element.closest('ol').children;
    // Tall notes: entry 0 has scrolled above the top (negative), so although it
    // still straddles the offset line, the active ring hands off to entry 1 —
    // the first still-visible note — instead of clinging to the past note.
    const tops = [-100, 500, 1100, 1700];
    Array.from(lis).forEach((li, i) => {
      li.getBoundingClientRect = () => ({ top: tops[i] });
    });
    window.dispatchEvent(new Event('scroll'));
    await new Promise((resolve) => requestAnimationFrame(() => resolve()));
    await wrapper.vm.$nextTick();

    expect(bubbles()[1].classes()).toContain('border-[3px]');
    expect(bubbles()[0].classes()).toContain('border-0');
    expect(bubbles()[2].classes()).toContain('border-0');
  });

  test('reaching the page bottom activates the last note past the offset', async () => {
    const { wrapper } = await mountView();
    const bubbles = () => wrapper.findAll('ol .transition-all');
    const lis = bubbles()[0].element.closest('ol').children;
    // Tops chosen so the offset rule alone would keep the newest entry active.
    const tops = [100, 300, 500, 700];
    Array.from(lis).forEach((li, i) => {
      li.getBoundingClientRect = () => ({ top: tops[i] });
    });

    // Simulate a scrollable page scrolled all the way to its bottom.
    const doc = document.documentElement;
    const innerH = Object.getOwnPropertyDescriptor(window, 'innerHeight');
    const scrollYd = Object.getOwnPropertyDescriptor(window, 'scrollY');
    Object.defineProperty(doc, 'scrollHeight', { configurable: true, get: () => 1000 });
    Object.defineProperty(window, 'innerHeight', { configurable: true, value: 600 });
    Object.defineProperty(window, 'scrollY', { configurable: true, value: 400 });
    try {
      window.dispatchEvent(new Event('scroll'));
      await new Promise((resolve) => requestAnimationFrame(() => resolve()));
      await wrapper.vm.$nextTick();

      expect(bubbles()[3].classes()).toContain('border-[3px]');
      expect(bubbles()[0].classes()).toContain('border-0');
    } finally {
      delete doc.scrollHeight;
      if (innerH) Object.defineProperty(window, 'innerHeight', innerH);
      if (scrollYd) Object.defineProperty(window, 'scrollY', scrollYd);
    }
  });

  test('body HTML from the API is rendered (markdown parsed server-side)', async () => {
    const { wrapper } = await mountView();
    // The API returns sanitized HTML (markdown already parsed). The timeline
    // renders it via MarkdownBody's v-html, so markdown-derived tags become
    // real elements rather than escaped text.
    expect(wrapper.find('strong').exists()).toBe(true);
    expect(wrapper.text()).toContain('Latest release with bold text.');
    expect(wrapper.text()).not.toContain('<strong>');
  });
});
