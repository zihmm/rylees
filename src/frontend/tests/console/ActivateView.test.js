import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';
import ActivateView from '../../src/apps/console/views/ActivateView.vue';
import * as api from '../../src/shared/api.js';

jest.mock('../../src/shared/api.js');

const Blank = { template: '<div><slot /></div>' };

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/activate', name: 'activate', component: ActivateView },
      { path: '/login', name: 'login', component: Blank },
    ],
  });
}

async function mountView(query = '') {
  const router = makeRouter();
  router.push('/activate' + query);
  await router.isReady();
  const wrapper = mount(ActivateView, { global: { plugins: [router] } });
  await flushPromises();
  return { wrapper };
}

beforeEach(() => {
  jest.clearAllMocks();
  setActivePinia(createPinia());
});

describe('ActivateView', () => {
  test('reads ?token= on mount and calls activate', async () => {
    api.activate.mockResolvedValue({ data: {} });
    await mountView('?token=abc123');
    expect(api.activate).toHaveBeenCalledWith('abc123');
  });

  test('200 shows success and a login link', async () => {
    api.activate.mockResolvedValue({ data: {} });
    const { wrapper } = await mountView('?token=abc123');
    expect(wrapper.text()).toContain('Your account has been activated.');
    const link = wrapper.findComponent({ name: 'RouterLink' });
    expect(wrapper.find('a').attributes('href')).toContain('/login');
  });

  test('404 shows an error message', async () => {
    api.activate.mockRejectedValue({ response: { status: 404 } });
    const { wrapper } = await mountView('?token=bad');
    expect(wrapper.text()).toContain(
      'Activation link is invalid or has already been used.'
    );
  });
});
