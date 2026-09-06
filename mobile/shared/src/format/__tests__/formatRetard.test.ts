import { formatRetard } from '../index';

/**
 * « 17732 min de retard » — vu sur une mission oubliée depuis douze jours, dans les DEUX
 * applications. La minute reste la bonne unité sous l'heure ; au-delà, elle cesse d'informer.
 */
describe('formatRetard', () => {
  it('garde les minutes sous une heure', () => {
    expect(formatRetard(0)).toBe('0 min');
    expect(formatRetard(1)).toBe('1 min');
    expect(formatRetard(59)).toBe('59 min');
  });

  it('passe aux heures au-delà', () => {
    expect(formatRetard(60)).toBe('1 h');
    expect(formatRetard(90)).toBe('1 h 30 min');
    expect(formatRetard(23 * 60)).toBe('23 h');
  });

  it('passe aux jours au-delà de vingt-quatre heures', () => {
    expect(formatRetard(24 * 60)).toBe('1 j');
    expect(formatRetard(17732)).toBe('12 j 7 h');
  });

  it('témoin : une valeur absente ou négative ne produit pas de texte absurde', () => {
    expect(formatRetard(null)).toBe('0 min');
    expect(formatRetard(undefined)).toBe('0 min');
    expect(formatRetard(-5)).toBe('0 min');
  });
});
