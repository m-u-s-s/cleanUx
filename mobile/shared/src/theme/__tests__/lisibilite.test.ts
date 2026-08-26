/**
 * AUCUNE COULEUR SEMANTIQUE NE DOIT TOMBER SOUS LE SEUIL — dans l'un OU l'autre theme.
 *
 * Le raisonnement d'origine ne portait que sur la nuit : « success.600 sur un fond de nuit
 * passe sous le seuil, success.500 le tient ». Personne n'avait fait le calcul dans l'autre
 * sens, et sur le blanc des cartes `success.600` rendait 3,77 et `warning.600` 3,18.
 *
 * Ce test refait les deux calculs a chaque execution : une palette qui derive se signale ici,
 * pas sur l'ecran d'un utilisateur.
 */
import { colors } from '../colors';

// Les surfaces reelles, lues dans useThemeColors : la plus dure de chaque theme.
const JOUR = '#ffffff';          // `card` en clair
const NUIT = '#111a2e';          // `cardSubtle` en sombre, plus clair que le fond de page

const SEUIL = 4.5;

const luminance = (hex: string): number => {
  const h = hex.replace('#', '');
  const canal = (i: number) => {
    const v = parseInt(h.slice(i, i + 2), 16) / 255;

    return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  };

  return 0.2126 * canal(0) + 0.7152 * canal(2) + 0.0722 * canal(4);
};

const contraste = (a: string, b: string): number => {
  const [x, y] = [luminance(a), luminance(b)];

  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
};

// Ce que `useThemeColors` rend pour chaque jeton, dans chaque theme.
const JETONS: Array<[string, string, string]> = [
  ['success', colors.success[700], colors.success[500]],
  ['warning', colors.warning[700], colors.warning[500]],
  ['danger', colors.danger[600], colors.danger[500]],
  ['brandText', colors.brand[600], colors.brand[400]],
];

describe('les couleurs de texte du theme', () => {
  it.each(JETONS)('%s tient le seuil dans les deux themes', (nom, clair, sombre) => {
    expect({ nom, theme: 'jour', ratio: contraste(clair, JOUR) })
      .toMatchObject({ ratio: expect.any(Number) });

    expect(contraste(clair, JOUR)).toBeGreaterThanOrEqual(SEUIL);
    expect(contraste(sombre, NUIT)).toBeGreaterThanOrEqual(SEUIL);
  });

  /*
   * TEMOIN. Sans lui, ce fichier passerait au vert si `contraste()` rendait toujours un grand
   * nombre — il mesurerait alors sa propre panne, pas la palette.
   */
  it('temoin : le calcul sait reconnaitre un couple illisible', () => {
    // Les valeurs qui ont motive la correction : elles DOIVENT echouer.
    expect(contraste(colors.success[600], JOUR)).toBeLessThan(SEUIL);
    expect(contraste(colors.warning[600], JOUR)).toBeLessThan(SEUIL);
    expect(contraste(colors.brand[500], NUIT)).toBeLessThan(SEUIL);

    // Et un couple evident doit passer, sinon le calcul est casse dans l'autre sens.
    expect(contraste('#000000', '#ffffff')).toBeCloseTo(21, 0);
  });
});
