import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PulseDot from './PulseDot.vue';

describe('PulseDot', () => {
  it('renders with default urgent variant', () => {
    const wrapper = mount(PulseDot);
    expect(wrapper.classes()).toContain('bg-semantic-urgent');
  });

  it('renders with success variant', () => {
    const wrapper = mount(PulseDot, { props: { variant: 'success' } });
    expect(wrapper.classes()).toContain('bg-semantic-success');
  });

  it('applies animate-pulse-glow class', () => {
    const wrapper = mount(PulseDot);
    expect(wrapper.classes()).toContain('animate-pulse-glow');
  });
});
