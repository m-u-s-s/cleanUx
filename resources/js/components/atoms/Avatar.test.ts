import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Avatar from './Avatar.vue';

describe('Avatar', () => {
  it('renders the initial of name', () => {
    const wrapper = mount(Avatar, { props: { name: 'Mohamed' } });
    expect(wrapper.text()).toBe('M');
  });

  it('renders uppercase initial', () => {
    const wrapper = mount(Avatar, { props: { name: 'sarah' } });
    expect(wrapper.text()).toBe('S');
  });

  it('renders custom size class', () => {
    const wrapper = mount(Avatar, { props: { name: 'A', size: 'lg' } });
    expect(wrapper.classes()).toContain('h-12');
    expect(wrapper.classes()).toContain('w-12');
  });

  it('defaults to md size', () => {
    const wrapper = mount(Avatar, { props: { name: 'A' } });
    expect(wrapper.classes()).toContain('h-11');
    expect(wrapper.classes()).toContain('w-11');
  });
});
