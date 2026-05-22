import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusTimeline from './StatusTimeline.vue';

describe('StatusTimeline', () => {
  const steps = [
    { id: 'accepted', label: 'Accepté' },
    { id: 'enroute', label: 'En route' },
    { id: 'arrived', label: 'Arrivé' },
    { id: 'mission', label: 'Mission' },
    { id: 'done', label: 'Terminé' },
  ];

  it('renders all steps', () => {
    const wrapper = mount(StatusTimeline, { props: { steps, currentId: 'enroute' } });
    expect(wrapper.findAll('[data-test="step"]')).toHaveLength(5);
  });

  it('marks done steps before current', () => {
    const wrapper = mount(StatusTimeline, { props: { steps, currentId: 'arrived' } });
    const dots = wrapper.findAll('[data-test="step-dot"]');
    expect(dots[0].classes()).toContain('is-done');
    expect(dots[1].classes()).toContain('is-done');
    expect(dots[2].classes()).toContain('is-active');
    expect(dots[3].classes()).not.toContain('is-done');
  });
});
