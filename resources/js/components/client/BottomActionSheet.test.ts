import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BottomActionSheet from './BottomActionSheet.vue';

describe('BottomActionSheet', () => {
  it('renders default slot content', () => {
    const wrapper = mount(BottomActionSheet, { slots: { default: '<p>Inner</p>' } });
    expect(wrapper.html()).toContain('Inner');
  });

  it('renders handle bar', () => {
    const wrapper = mount(BottomActionSheet);
    expect(wrapper.find('[data-test="sheet-handle"]').exists()).toBe(true);
  });
});
