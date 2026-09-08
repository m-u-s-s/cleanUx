import { authStylesFor, fondDAuthentification, CANVAS } from '../authShell';
import type { ThemeTokens } from '../../theme/useThemeColors';

const jetons = (isDark: boolean): ThemeTokens =>
  ({
    isDark,
    card: isDark ? '#111a2e' : '#ffffff',
    border: isDark ? 'rgba(232, 238, 252, 0.10)' : '#e2e8f0',
    text: isDark ? '#e8eefc' : '#171717',
    textSecondary: isDark ? '#93a4c6' : '#525252',
    danger: isDark ? '#ef4444' : '#dc2626',
  }) as unknown as ThemeTokens;

/**
 * L'AUTHENTIFICATION ÉTAIT CLAIRE EN DUR, DANS LES DEUX APPLICATIONS.
 *
 * `authShell` fixait `CANVAS` et `'#ffffff'`, au motif écrit que « le kit partagé est entièrement
 * conçu pour une surface claire ». Cette prémisse a expiré : `TextInput`, `Button` et `Divider`
 * consultent le thème depuis le 2026-08-26. Restait une porte d'entrée blanche sur un téléphone
 * en sombre — vu sur l'émulateur le 2026-09-08, Android en sombre et l'écran tout blanc.
 */
describe('authShell suit le thème', () => {
  it('rend le fond clair à l’identique', () => {
    expect(fondDAuthentification(false)).toBe(CANVAS);
    expect(authStylesFor(jetons(false)).container.backgroundColor).toBe(CANVAS);
  });

  it('laisse passer la toile en sombre plutôt que de peindre du noir', () => {
    // `NightShell` peint déjà sa toile Skia dessous : la couvrir la rendrait invisible ICI SEULEMENT.
    expect(fondDAuthentification(true)).toBe('transparent');
  });

  it('la carte et son bord suivent le thème', () => {
    const sombre = authStylesFor(jetons(true));
    const clair = authStylesFor(jetons(false));

    expect(sombre.card.backgroundColor).toBe('#111a2e');
    expect(clair.card.backgroundColor).toBe('#ffffff');
    expect(sombre.card.borderColor).not.toBe(clair.card.borderColor);
  });

  it('témoin : plus aucune couleur en dur dans la feuille partagée', () => {
    const sombre = authStylesFor(jetons(true));

    // Le blanc de la carte était le symptôme le plus visible ; il ne doit plus apparaître en sombre.
    for (const cle of ['container', 'card', 'subtitle', 'termsText'] as const) {
      const valeur = JSON.stringify(sombre[cle]);
      expect(valeur).not.toContain('#ffffff');
      expect(valeur).not.toContain(CANVAS);
    }
  });
});
