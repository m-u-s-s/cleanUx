import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FabUrgence from './FabUrgence.vue';

describe('FabUrgence', () => {
  it('renders fixed positioned button', () => {
    const wrapper = mount(FabUrgence);
    expect(wrapper.find('button').classes()).toContain('fixed');
  });

  it('emits trigger on click', async () => {
    const wrapper = mount(FabUrgence);
    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted('trigger')).toHaveLength(1);
  });
});
