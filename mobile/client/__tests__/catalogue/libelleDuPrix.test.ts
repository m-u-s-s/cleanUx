import { renderHook } from '@testing-library/react-native';
import { useTraduction } from '@/i18n';
import { useThemeColors } from '@/theme/useThemeColors';
import { libelleDuPrix } from '@/screens/catalogue/libelleDuPrix';

const metier = (floor_price_cents: number | null, hourly = false) => ({
  slug: 'plomberie', name: 'Plomberie', icon: 'wrench', short_description: null, floor_price_cents, hourly,
});

describe('libelleDuPrix', () => {
  it("annonce un plancher hors taxe, arrondi a l'euro", () => {
    const { result } = renderHook(() => useTraduction());
    const tr = result.current.t;
    expect(libelleDuPrix(metier(8500), 'EUR', tr)).toMatch(/^des 85\s€ hors taxe$/);
  });

  it('lit un tarif horaire par heure', () => {
    const { result } = renderHook(() => useTraduction());
    const tr = result.current.t;
    expect(libelleDuPrix(metier(4500, true), 'EUR', tr)).toMatch(/^des 45\s€\/h hors taxe$/);
  });

  it('temoin : un tarif au forfait ne porte pas /h', () => {
    const { result } = renderHook(() => useTraduction());
    const tr = result.current.t;
    expect(libelleDuPrix(metier(4500, false), 'EUR', tr)).not.toContain('/h');
  });

  it('sans plancher, le prix sort des reponses', () => {
    const { result } = renderHook(() => useTraduction());
    const tr = result.current.t;
    expect(libelleDuPrix(metier(null), 'EUR', tr)).toBe('Prix selon vos reponses');
  });

  it('suit la devise de la zone', () => {
    const { result } = renderHook(() => useTraduction());
    const tr = result.current.t;
    expect(libelleDuPrix(metier(8500), 'MAD', tr)).toContain('MAD');
  });
});

describe('le jeton argent', () => {
  it('existe dans le theme', () => {
    const { result } = renderHook(() => useThemeColors());
    expect(typeof result.current.argent).toBe('string');
  });
});
