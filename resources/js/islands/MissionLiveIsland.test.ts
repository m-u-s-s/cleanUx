import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import MissionLiveIsland from './MissionLiveIsland.vue';

describe('MissionLiveIsland', () => {
  const baseProps = {
    missionId: 42,
    initialEtaMinutes: 8,
    initialDistanceKm: 2.1,
    initialCurrentStep: 'enroute',
    provider: {
      name: 'Karim B.',
      rating: 4.9,
      missionsCount: 287,
      label: 'Serrurier certifié',
    },
    isUrgent: true,
  };

  it('renders provider name', () => {
    const wrapper = mount(MissionLiveIsland, { props: baseProps });
    expect(wrapper.text()).toContain('Karim B.');
  });

  it('renders urgency banner when urgent', () => {
    const wrapper = mount(MissionLiveIsland, { props: baseProps });
    expect(wrapper.text()).toContain('URGENCE');
  });

  it('does not render urgency banner when not urgent', () => {
    const wrapper = mount(MissionLiveIsland, { props: { ...baseProps, isUrgent: false } });
    expect(wrapper.text()).not.toContain('URGENCE');
  });

  it('dispatches scan event on QR cta', async () => {
    const dispatched: CustomEvent[] = [];
    const handler = (e: Event) => dispatched.push(e as CustomEvent);
    window.addEventListener('cleanux:mission-scan', handler);

    const wrapper = mount(MissionLiveIsland, { props: { ...baseProps, initialCurrentStep: 'arrived' } });
    await wrapper.findComponent({ name: 'QrScanCta' }).find('button').trigger('click');

    expect(dispatched).toHaveLength(1);
    expect(dispatched[0].detail.missionId).toBe(42);

    window.removeEventListener('cleanux:mission-scan', handler);
  });
});
