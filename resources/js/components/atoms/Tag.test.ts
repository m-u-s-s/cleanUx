import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Tag from './Tag.vue';

describe('Tag', () => {
  it('renders slot content', () => {
    const wrapper = mount(Tag, { slots: { default: 'Premium' } });
    expect(wrapper.text()).toBe('Premium');
  });

  it('applies variant primary by default', () => {
    const wrapper = mount(Tag, { slots: { default: 'x' } });
    expect(wrapper.classes()).toContain('text-semantic-primary');
  });

  it('applies variant urgent', () => {
    const wrapper = mount(Tag, { props: { variant: 'urgent' }, slots: { default: 'x' } });
    expect(wrapper.classes()).toContain('text-semantic-urgent');
  });
});
