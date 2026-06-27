import { mount, flushPromises } from '@vue/test-utils';
import TokenField from '../../src/apps/console/components/TokenField.vue';

function mountField(token = 'ryl_secret_token_123') {
  return mount(TokenField, { props: { token } });
}

afterEach(() => {
  jest.restoreAllMocks();
  delete navigator.clipboard;
});

describe('TokenField copy button', () => {
  test('writes the token via the async Clipboard API', async () => {
    const writeText = jest.fn().mockResolvedValue();
    Object.assign(navigator, { clipboard: { writeText } });

    const wrapper = mountField();
    await wrapper.find('button[aria-label="Copy to clipboard"]').trigger('click');
    await flushPromises();

    expect(writeText).toHaveBeenCalledWith('ryl_secret_token_123');
    expect(wrapper.find('button[aria-label="Copied"]').exists()).toBe(true);
  });

  test('falls back to execCommand when the Clipboard API is unavailable', async () => {
    // Simulate an insecure context: no navigator.clipboard at all.
    delete navigator.clipboard;
    const execCommand = jest.fn().mockReturnValue(true);
    document.execCommand = execCommand;

    const wrapper = mountField();
    await wrapper.find('button[aria-label="Copy to clipboard"]').trigger('click');
    await flushPromises();

    expect(execCommand).toHaveBeenCalledWith('copy');
    expect(wrapper.find('button[aria-label="Copied"]').exists()).toBe(true);
  });

  test('falls back to execCommand when the Clipboard API rejects', async () => {
    const writeText = jest.fn().mockRejectedValue(new Error('denied'));
    Object.assign(navigator, { clipboard: { writeText } });
    const execCommand = jest.fn().mockReturnValue(true);
    document.execCommand = execCommand;

    const wrapper = mountField();
    await wrapper.find('button[aria-label="Copy to clipboard"]').trigger('click');
    await flushPromises();

    expect(writeText).toHaveBeenCalledWith('ryl_secret_token_123');
    expect(execCommand).toHaveBeenCalledWith('copy');
    expect(wrapper.find('button[aria-label="Copied"]').exists()).toBe(true);
  });

  test('does nothing when the token is empty', async () => {
    const writeText = jest.fn().mockResolvedValue();
    Object.assign(navigator, { clipboard: { writeText } });

    const wrapper = mountField('');
    await wrapper.find('button[aria-label="Copy to clipboard"]').trigger('click');
    await flushPromises();

    expect(writeText).not.toHaveBeenCalled();
  });
});
