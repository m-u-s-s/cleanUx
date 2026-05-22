import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import QrScanCta from './QrScanCta.vue';

describe('QrScanCta', () => {
  it('renders title and subtitle', () => {
    const wrapper = mount(QrScanCta, { props: { title: 'Scanner', subtitle: '6 chiffres' } });
    expect(wrapper.text()).toContain('Scanner');
    expect(wrapper.text()).toContain('6 chiffres');
  });

  it('emits scan on click', async () => {
    const wrapper = mount(QrScanCta, { props: { title: 't', subtitle: 's' } });
    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted('scan')).toHaveLength(1);
  });

  it('is disabled when disabled prop true', () => {
    const wrapper = mount(QrScanCta, { props: { title: 't', subtitle: 's', disabled: true } });
    expect(wrapper.find('button').attributes('disabled')).toBeDefined();
  });
});
