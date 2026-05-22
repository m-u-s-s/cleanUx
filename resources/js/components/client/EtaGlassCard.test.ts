import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import EtaGlassCard from './EtaGlassCard.vue';

describe('EtaGlassCard', () => {
  const props = {
    etaMinutes: 8,
    distanceKm: 2.1,
    providerName: 'Karim B.',
    steps: [
      { id: 'accepted', label: 'Accepté' },
      { id: 'enroute', label: 'En route' },
    ],
    currentStep: 'enroute',
  };

  it('renders ETA minutes', () => {
    const wrapper = mount(EtaGlassCard, { props });
    expect(wrapper.text()).toContain('8');
    expect(wrapper.text()).toContain('min');
  });

  it('renders provider name and distance', () => {
    const wrapper = mount(EtaGlassCard, { props });
    expect(wrapper.text()).toContain('Karim B.');
    expect(wrapper.text()).toContain('2.1');
  });
});
