import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import QuickActionGrid from './QuickActionGrid.vue';

describe('QuickActionGrid', () => {
  const actions = [
    { id: 'urgent', icon: '⚡', label: 'Urgent' },
    { id: 'rebook', icon: '🔁', label: 'Rebook' },
    { id: 'address', icon: '📍', label: 'Adresses' },
    { id: 'help', icon: '💬', label: 'Aide' },
  ];

  it('renders all actions', () => {
    const wrapper = mount(QuickActionGrid, { props: { actions } });
    expect(wrapper.findAll('[data-test="quick-action"]')).toHaveLength(4);
    expect(wrapper.text()).toContain('Urgent');
    expect(wrapper.text()).toContain('Rebook');
  });

  it('emits action with id when clicked', async () => {
    const wrapper = mount(QuickActionGrid, { props: { actions } });
    await wrapper.findAll('[data-test="quick-action"]')[0].trigger('click');
    expect(wrapper.emitted('action')).toEqual([['urgent']]);
  });
});
