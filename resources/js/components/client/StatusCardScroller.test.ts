import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusCardScroller from './StatusCardScroller.vue';

describe('StatusCardScroller', () => {
  const cards = [
    { id: 'loyalty', label: 'Fidélité', value: '320', sub: '+80 pts pour Gold', badge: 'SILVER' },
    { id: 'credits', label: 'Crédits', value: '25 €', sub: 'Expire 30/06' },
    { id: 'referral', label: 'Parrainage', value: '3 invités', sub: '2 inscrits ✓' },
  ];

  it('renders all cards', () => {
    const wrapper = mount(StatusCardScroller, { props: { cards } });
    expect(wrapper.findAll('[data-test="status-card"]')).toHaveLength(3);
    expect(wrapper.text()).toContain('Fidélité');
    expect(wrapper.text()).toContain('SILVER');
  });

  it('emits select with card id', async () => {
    const wrapper = mount(StatusCardScroller, { props: { cards } });
    await wrapper.findAll('[data-test="status-card"]')[0].trigger('click');
    expect(wrapper.emitted('select')).toEqual([['loyalty']]);
  });
});
