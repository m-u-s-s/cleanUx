/**
 * Les jetons de thème — la source unique des couleurs.
 *
 * Le mode sombre existait déjà et ne fonctionnait pas : les composants écrivaient leurs couleurs
 * en dur. La réparation passe par un jeu de jetons assez complet pour qu'aucun composant n'ait de
 * raison d'inventer une couleur. Ce fichier fige ce jeu.
 */
import { renderHook } from '@testing-library/react-native';

const mockScheme = { colorScheme: 'dark' as 'dark' | 'light', mode: 'dark', setMode: jest.fn() };

jest.mock('@/theme/useColorScheme', () => ({
  useColorScheme: () => mockScheme,
}));

import { useThemeColors } from '@/theme/useThemeColors';

const enSombre = () => {
  mockScheme.colorScheme = 'dark';

  return renderHook(() => useThemeColors()).result.current;
};

const enClair = () => {
  mockScheme.colorScheme = 'light';

  return renderHook(() => useThemeColors()).result.current;
};

describe('jetons de thème', () => {
  it('expose les jetons dont la matière verre a besoin', () => {
    const t = enSombre();

    for (const jeton of ['glass', 'glassStrong', 'glassBorder', 'textOnGlass', 'mutedOnGlass', 'glow'] as const) {
      expect(t[jeton]).toBeTruthy();
    }
  });

  it('dit s’il fait sombre, pour que les composants n’aient pas à le redéduire', () => {
    // Sans ce drapeau, chaque composant réimporterait useColorScheme et comparerait la chaîne —
    // autant d'occasions de se tromper.
    expect(enSombre().isDark).toBe(true);
    expect(enClair().isDark).toBe(false);
  });

  it('les surfaces de verre sont translucides, jamais opaques', () => {
    const t = enSombre();

    // Une surface opaque n'est plus du verre : elle masquerait le fond au lieu de le filtrer.
    expect(t.glass).toMatch(/^rgba\(/);
    expect(t.glassStrong).toMatch(/^rgba\(/);
    expect(t.glassBorder).toMatch(/^rgba\(/);
  });

  it('le voile de verre garde un plancher d’opacité', () => {
    const opacite = Number(/rgba\([^)]*,\s*([\d.]+)\)/.exec(enSombre().glass)?.[1]);

    // Le contraste du texte est garanti par le VOILE, pas par le flou : un flou mélange, il ne
    // fonce pas. Sous ce plancher, un texte devient illisible dès que quelque chose de clair
    // défile derrière.
    expect(opacite).toBeGreaterThanOrEqual(0.05);
  });

  it('le fond sombre adopte la palette nuit du projet', () => {
    // Et non un gris neutre : c'est cette palette qui porte l'ambiance validée, et en introduire
    // une seconde ferait diverger deux définitions du même noir.
    expect(enSombre().bg).toBe('#070b14');
  });

  it('ne touche pas au mode clair', () => {
    const t = enClair();

    // Un prestataire en plein soleil a besoin de contraste, pas de translucidité.
    expect(t.bg).toBe('#fafafa');
    expect(t.card).toBe('#ffffff');
    expect(t.glow).toBe('transparent');
  });
});
