/**
 * Les barres : onglets et feuilles.
 *
 * POURQUOI UNE FONCTION PURE PLUTÔT QU'UN TEST DE NAVIGATEUR. Monter un `Tab.Navigator` pour lire
 * la couleur de sa barre demande un conteneur de navigation, quatre écrans réels et leurs
 * dépendances — on testerait alors surtout React Navigation. L'apparence de la barre est extraite
 * dans une fonction qui prend le thème et rend des styles : c'est elle qui porte la décision, donc
 * c'est elle qu'on vérifie.
 *
 * CE QUE ÇA ATTRAPE. Une barre restée opaque en sombre : elle couperait le fond nuit d'un trait
 * plat en bas de chaque écran, et les gouttes s'arrêteraient net sur une ligne. C'est visible, mais
 * seulement si quelqu'un regarde — et personne ne regarde le bas de l'écran.
 */
import React from 'react';
import { render, screen } from '@testing-library/react-native';

const mockScheme = { colorScheme: 'dark' as 'dark' | 'light', mode: 'dark', setMode: jest.fn() };

jest.mock('@/theme/useColorScheme', () => ({ useColorScheme: () => mockScheme }));

import { useThemeColors } from '@/theme/useThemeColors';


describe('la remontee de la barre d onglets', () => {
  beforeEach(() => {
    mockScheme.colorScheme = 'dark';
  });

  /**
   * LE DEGRADE EST UNE GARDE, PAS UNE DECORATION.
   *
   * La barre n'a plus de plaque : c'est son degrade qui porte la lisibilite. Son arret du MILIEU
   * doit valoir au moins l'opacite de `glass` — c'est la densite sur laquelle tout le garde-fou de
   * contraste est calcule. Le baisser rend les libellés illisibles des que l'iceberg defile
   * derriere, et la panne ne se voit que sur l'appareil.
   */
  it.each(['dark', 'light'] as const)('atteint la densite du verre a mi-hauteur (%s)', schema => {
    mockScheme.colorScheme = schema;
    const t = jetons();

    expect(opacite(t.remonteeMilieu)).toBeGreaterThanOrEqual(opacite(t.glass));
  });

  /** Transparent en haut, plein en bas : sans cela il n'y a plus de remontee, mais une plaque. */
  it.each(['dark', 'light'] as const)('part de rien et finit plein (%s)', schema => {
    mockScheme.colorScheme = schema;
    const t = jetons();

    expect(opacite(t.remonteeHaut)).toBe(0);
    expect(opacite(t.remonteeBas)).toBeGreaterThan(0.98);
  });

  /**
   * LA BARRE N'INTRODUIT PAS UNE TROISIEME COULEUR. Les trois arrets portent le SOL du theme —
   * l'abysse en nuit, le blanc en jour. Un bleu choisi a l'oeil ici ferait un bandeau qu'on
   * distingue du fond des qu'il n'y a rien derriere.
   */
  it.each(['dark', 'light'] as const)('porte le sol du theme, et lui seul (%s)', schema => {
    mockScheme.colorScheme = schema;
    const t = jetons();
    const attendu = schema === 'dark' ? '4, 16, 28' : '255, 255, 255';

    for (const arret of [t.remonteeHaut, t.remonteeMilieu, t.remonteeBas]) {
      expect(arret).toContain(attendu);
    }
  });

  /*
   * TEMOIN. Sans lui, `opacite()` pourrait rendre 1 pour tout et les deux premiers tests
   * passeraient en mesurant leur propre panne.
   */
  it('temoin : la lecture d opacite sait distinguer deux voiles', () => {
    expect(opacite('rgba(4, 16, 28, 0)')).toBe(0);
    expect(opacite('rgba(4, 16, 28, 0.9)')).toBeCloseTo(0.9, 5);
    expect(opacite('rgba(4, 16, 28, 0.995)')).toBeCloseTo(0.995, 5);
  });
});

/** L'opacite d'un `rgba(...)`. Une couleur opaque sans canal alpha vaut 1. */
function opacite(couleur: string): number {
  const m = /rgba\([^)]*,\s*([0-9.]+)\s*\)/.exec(couleur);

  return m ? Number(m[1]) : 1;
}

/** Rend le hook accessible hors composant, via un montage jetable. */
function jetons() {
  let capture: ReturnType<typeof useThemeColors> | undefined;

  function Sonde() {
    capture = useThemeColors();

    return null;
  }

  render(<Sonde />);

  if (!capture) {
    throw new Error('le hook n’a pas été appelé');
  }

  return capture;
}

/** Réduit un style React Native — objet, tableau, imbriqué — à un seul objet. */
function aplat(style: unknown): Record<string, unknown> {
  if (Array.isArray(style)) {
    return style.reduce<Record<string, unknown>>((acc, s) => ({ ...acc, ...aplat(s) }), {});
  }

  return (style ?? {}) as Record<string, unknown>;
}
