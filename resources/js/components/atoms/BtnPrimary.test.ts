import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BtnPrimary from './BtnPrimary.vue';

describe('BtnPrimary', () => {
  it('renders slot content', () => {
    const wrapper = mount(BtnPrimary, { slots: { default: 'Voir' } });
    expect(wrapper.text()).toBe('Voir');
  });

  it('emits click', async () => {
    const wrapper = mount(BtnPrimary, { slots: { default: 'x' } });
    await wrapper.trigger('click');
    expect(wrapper.emitted('click')).toHaveLength(1);
  });

  it('is disabled when prop disabled is true', () => {
    const wrapper = mount(BtnPrimary, { props: { disabled: true }, slots: { default: 'x' } });
    expect(wrapper.attributes('disabled')).toBeDefined();
  });
});
