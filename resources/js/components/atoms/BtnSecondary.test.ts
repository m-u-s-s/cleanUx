import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BtnSecondary from './BtnSecondary.vue';

describe('BtnSecondary', () => {
  it('renders slot content', () => {
    const wrapper = mount(BtnSecondary, { slots: { default: 'Suivre' } });
    expect(wrapper.text()).toBe('Suivre');
  });

  it('emits click', async () => {
    const wrapper = mount(BtnSecondary, { slots: { default: 'x' } });
    await wrapper.trigger('click');
    expect(wrapper.emitted('click')).toHaveLength(1);
  });
});
