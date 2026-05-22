import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import AdaptiveHero from './AdaptiveHero.vue';

describe('AdaptiveHero', () => {
  const props = {
    eyebrow: 'Prochain rendez-vous',
    title: 'Toiturier · Demain 14h00',
    meta: '23 av. Louise',
    tags: ['Devis 425€', 'Pré-autorisé'],
  };

  it('renders eyebrow, title, meta', () => {
    const wrapper = mount(AdaptiveHero, { props });
    expect(wrapper.text()).toContain('Prochain rendez-vous');
    expect(wrapper.text()).toContain('Toiturier · Demain 14h00');
    expect(wrapper.text()).toContain('23 av. Louise');
  });

  it('renders all tags', () => {
    const wrapper = mount(AdaptiveHero, { props });
    expect(wrapper.text()).toContain('Devis 425€');
    expect(wrapper.text()).toContain('Pré-autorisé');
  });

  it('emits primary-action click', async () => {
    const wrapper = mount(AdaptiveHero, { props });
    await wrapper.find('[data-test="primary-action"]').trigger('click');
    expect(wrapper.emitted('primary-action')).toHaveLength(1);
  });

  it('emits secondary-action click', async () => {
    const wrapper = mount(AdaptiveHero, { props });
    await wrapper.find('[data-test="secondary-action"]').trigger('click');
    expect(wrapper.emitted('secondary-action')).toHaveLength(1);
  });
});
