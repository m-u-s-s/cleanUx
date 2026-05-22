import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ServiceTile from './ServiceTile.vue';

describe('ServiceTile', () => {
  it('renders emoji and name', () => {
    const wrapper = mount(ServiceTile, { props: { emoji: '🔑', name: 'Serrurier' } });
    expect(wrapper.text()).toContain('🔑');
    expect(wrapper.text()).toContain('Serrurier');
  });

  it('emits select on click with name', async () => {
    const wrapper = mount(ServiceTile, { props: { emoji: '🔑', name: 'Serrurier' } });
    await wrapper.trigger('click');
    expect(wrapper.emitted('select')).toEqual([['Serrurier']]);
  });
});
