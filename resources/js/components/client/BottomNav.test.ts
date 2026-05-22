import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BottomNav from './BottomNav.vue';

describe('BottomNav', () => {
  const items = [
    { id: 'home', icon: '⌂', label: 'Accueil' },
    { id: 'search', icon: '⌕', label: 'Recherche' },
    { id: 'bookings', icon: '▦', label: 'RDV' },
    { id: 'alerts', icon: '○', label: 'Alertes' },
    { id: 'profile', icon: '⊙', label: 'Profil' },
  ];

  it('renders all items', () => {
    const wrapper = mount(BottomNav, { props: { items, activeId: 'home' } });
    expect(wrapper.findAll('[data-test="nav-item"]')).toHaveLength(5);
  });

  it('marks active item', () => {
    const wrapper = mount(BottomNav, { props: { items, activeId: 'search' } });
    const navItems = wrapper.findAll('[data-test="nav-item"]');
    expect(navItems[1].classes()).toContain('is-active');
  });

  it('emits navigate with id', async () => {
    const wrapper = mount(BottomNav, { props: { items, activeId: 'home' } });
    await wrapper.findAll('[data-test="nav-item"]')[2].trigger('click');
    expect(wrapper.emitted('navigate')).toEqual([['bookings']]);
  });
});
