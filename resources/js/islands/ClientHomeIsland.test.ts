import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ClientHomeIsland from './ClientHomeIsland.vue';

describe('ClientHomeIsland', () => {
  const props = {
    userName: 'Mohamed',
    initialTheme: 'light' as const,
    upcomingBooking: {
      title: 'Toiturier · Demain 14h00',
      meta: '23 av. Louise',
      tags: ['Devis 425 €', 'Pré-autorisé'],
    },
    statusCards: [
      { id: 'loyalty', label: 'Fidélité', value: '320', sub: 'Silver' },
    ],
    services: [
      { emoji: '🔑', name: 'Serrurier' },
    ],
    userEmail: 'mohamed@example.com',
    profileUrl: '/dashboard/client/profil',
    notificationsUrl: '/dashboard/client/notifications',
    helpUrl: '/help/faq',
    logoutUrl: '/logout',
    csrfToken: 'csrf-token-test',
    themePreference: 'auto' as const,
  };

  it('renders user name', () => {
    const wrapper = mount(ClientHomeIsland, { props });
    expect(wrapper.text()).toContain('Mohamed');
  });

  it('renders upcoming booking title', () => {
    const wrapper = mount(ClientHomeIsland, { props });
    expect(wrapper.text()).toContain('Toiturier · Demain 14h00');
  });

  it('renders status cards', () => {
    const wrapper = mount(ClientHomeIsland, { props });
    expect(wrapper.text()).toContain('Fidélité');
  });

  it('dispatches window event on quick action', async () => {
    const dispatched: CustomEvent[] = [];
    const handler = (e: Event) => dispatched.push(e as CustomEvent);
    window.addEventListener('cleanux:client-action', handler);

    const wrapper = mount(ClientHomeIsland, { props });
    await wrapper.findAll('[data-test="quick-action"]')[0].trigger('click');

    expect(dispatched).toHaveLength(1);
    expect(dispatched[0].detail.id).toBe('urgent');

    window.removeEventListener('cleanux:client-action', handler);
  });

  it('opens user menu sheet on avatar tap', async () => {
    const wrapper = mount(ClientHomeIsland, { props, attachTo: document.body });

    // Find Avatar component in the header
    const avatar = wrapper.findComponent({ name: 'Avatar' });
    expect(avatar.exists()).toBe(true);

    await avatar.trigger('click');

    // UserMenuSheet uses Teleport — content appears in document.body
    expect(document.body.querySelector('[data-test="sheet-container"]')).not.toBeNull();
    wrapper.unmount();
  });
});
