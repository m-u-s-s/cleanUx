/**
 * Le mode clair est identique à avant le chantier verre.
 *
 * POURQUOI CE TEST PLUTÔT QU'UN COUP D'ŒIL. « Le mode clair ne doit pas changer » est la
 * contrainte la plus facile à casser sans s'en apercevoir : on travaille en sombre quand on
 * travaille le sombre, et une couleur claire décalée d'un ton ne se remarque que des semaines plus
 * tard, sur l'appareil de quelqu'un d'autre.
 *
 * CE QU'IL VÉRIFIE. Chaque jeton introduit pour le sombre rend, en clair, exactement la valeur qui
 * occupait sa place avant. Les valeurs ci-dessous sont donc écrites EN DUR volontairement : les
 * relire depuis la palette rendrait le test tautologique — il suivrait toute dérive au lieu de la
 * signaler.
 */
import { renderHook } from '@testing-library/react-native';

const mockScheme = { colorScheme: 'light' as 'dark' | 'light', mode: 'light', setMode: jest.fn() };

jest.mock('@/theme/useColorScheme', () => ({ useColorScheme: () => mockScheme }));

import { useThemeColors } from '@/theme/useThemeColors';
import { apparenceDeBarre, fondDeFeuille } from '@/ui/glassBars';

/** Les valeurs d'origine, relevées avant le chantier. */
const AVANT = {
  page: '#fafafa', // était `colors.surface[50]` sur les conteneurs pleine page
  carteDiscrete: '#fafafa', // était `colors.surface[50]` sur les cartes
  carte: '#ffffff',
  saisie: '#fafafa', // `TextInput` portait `bg`, qui vaut la même chose en clair
  ongletInactif: '#a3a3a3', // était `colors.surface[400]`
  bordure: '#e5e5e5',
} as const;

describe('le mode clair n’a pas bougé', () => {
  beforeEach(() => {
    mockScheme.colorScheme = 'light';
  });

  it('rend les surfaces à leurs valeurs d’origine', () => {
    const { result } = renderHook(() => useThemeColors());
    const t = result.current;

    expect(t.isDark).toBe(false);
    expect(t.page).toBe(AVANT.page);
    expect(t.cardSubtle).toBe(AVANT.carteDiscrete);
    expect(t.card).toBe(AVANT.carte);
    expect(t.inputBg).toBe(AVANT.saisie);
    expect(t.textMuted).toBe(AVANT.ongletInactif);
    expect(t.border).toBe(AVANT.bordure);
  });

  it('laisse la barre d’onglets pleine et bordée', () => {
    const { result } = renderHook(() => useThemeColors());
    const barre = apparenceDeBarre(result.current);

    expect(barre.tabBarStyle.backgroundColor).toBe(AVANT.page);
    expect(barre.tabBarStyle.borderTopColor).toBe(AVANT.bordure);
    // Aucune plaque de verre : c'est ce qui distingue le clair du sombre.
    expect(barre.tabBarBackground).toBeUndefined();
  });

  it('laisse les feuilles modales pleines', () => {
    const { result } = renderHook(() => useThemeColors());
    const feuille = fondDeFeuille(result.current);

    expect(feuille.backgroundColor).toBe(AVANT.page);
    expect(feuille.borderWidth).toBeUndefined();
  });

  it('ne pose aucun voile translucide sur les surfaces du clair', () => {
    const { result } = renderHook(() => useThemeColors());
    const t = result.current;

    /*
     * Les jetons de verre EXISTENT en clair — leur absence forcerait chaque composant à tester le
     * mode avant de lire un jeton. Ce qui compte est qu'aucune SURFACE du mode clair ne les
     * emploie : les composants retombent tous sur leur rendu plein, ce que vérifient leurs propres
     * tests. Ici on vérifie seulement que les surfaces pleines le sont restées.
     */
    for (const surface of [t.page, t.card, t.cardSubtle, t.inputBg]) {
      expect(surface).not.toMatch(/^rgba\(/);
    }

    // Et que la lueur de marque, qui n'aurait aucun sens en plein jour, est bien éteinte.
    expect(t.glow).toBe('transparent');
  });
});
