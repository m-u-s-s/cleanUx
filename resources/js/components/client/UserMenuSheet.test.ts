import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import UserMenuSheet from './UserMenuSheet.vue';

describe('UserMenuSheet', () => {
  const baseProps = {
    open: true,
    userName: 'Beta Tester',
    userEmail: 'beta@cleanux.test',
    initialTheme: 'auto' as const,
    profileUrl: '/dashboard/client/profil',
    notificationsUrl: '/dashboard/client/notifications',
    helpUrl: '/help/faq',
    logoutUrl: '/logout',
    csrfToken: 'test-csrf-token',
  };

  it('renders when open is true', () => {
    const wrapper = mount(UserMenuSheet, { props: baseProps, attachTo: document.body });
    expect(document.body.querySelector('[data-test="sheet-container"]')).not.toBeNull();
    expect(document.body.textContent).toContain('Beta Tester');
    wrapper.unmount();
  });

  it('does not render content when open is false', () => {
    const wrapper = mount(UserMenuSheet, { props: { ...baseProps, open: false }, attachTo: document.body });
    expect(document.body.querySelector('[data-test="sheet-container"]')).toBeNull();
    wrapper.unmount();
  });

  it('emits close on backdrop click', async () => {
    const wrapper = mount(UserMenuSheet, { props: baseProps, attachTo: document.body });
    const backdrop = document.body.querySelector('[data-test="backdrop"]') as HTMLElement;
    backdrop?.click();
    expect(wrapper.emitted('close')).toHaveLength(1);
    wrapper.unmount();
  });

  it('emits theme-change with dark when dark radio is selected', async () => {
    const wrapper = mount(UserMenuSheet, { props: baseProps, attachTo: document.body });
    const darkBtn = document.body.querySelector('[data-test="theme-dark"]') as HTMLElement;
    darkBtn?.click();
    expect(wrapper.emitted('theme-change')).toEqual([['dark']]);
    wrapper.unmount();
  });

  it('renders logout form with csrf token', () => {
    const wrapper = mount(UserMenuSheet, { props: baseProps, attachTo: document.body });
    const form = document.body.querySelector('[data-test="logout-form"]') as HTMLFormElement;
    expect(form).not.toBeNull();
    expect(form.getAttribute('action')).toBe('/logout');
    const tokenInput = form.querySelector('input[name="_token"]') as HTMLInputElement;
    expect(tokenInput.value).toBe('test-csrf-token');
    wrapper.unmount();
  });
});
