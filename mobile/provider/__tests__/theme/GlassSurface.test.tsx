/**
 * La surface de verre — cartes, panneaux, barres.
 *
 * CE QUE CES TESTS PROTÈGENT. Deux régressions probables, et une seule est visible à l'œil.
 *
 * La première : quelqu'un applique le verre partout, y compris en mode clair, et le mode clair
 * change. C'est la contrainte la plus facile à casser sans s'en apercevoir, parce qu'on développe
 * en sombre quand on travaille sur le sombre.
 *
 * La seconde : le flou disparaît sur un appareil qui ne le gère pas, et il ne reste RIEN — un
 * panneau invisible sur un fond nuit, avec du texte flottant dans le vide. Le voile doit tenir
 * seul. C'est le test qu'on n'écrit pas spontanément, parce que sur la machine du développeur le
 * flou marche toujours.
 */
import React from 'react';
import { Text } from 'react-native';
import { render, screen } from '@testing-library/react-native';

const mockScheme = { colorScheme: 'dark' as 'dark' | 'light', mode: 'dark', setMode: jest.fn() };

jest.mock('@/theme/useColorScheme', () => ({ useColorScheme: () => mockScheme }));

import { GlassSurface } from '@/ui/GlassSurface';

/** Le voile est masqué aux lecteurs d'écran : il faut le demander pour l'atteindre. */
const MASQUE = { includeHiddenElements: true } as const;

describe('GlassSurface', () => {
  beforeEach(() => {
    mockScheme.colorScheme = 'dark';
  });

  it('reste une surface pleine et opaque en mode clair', () => {
    mockScheme.colorScheme = 'light';

    render(
      <GlassSurface>
        <Text>contenu</Text>
      </GlassSurface>,
    );

    // Pas de flou en clair : le mode clair n'est pas touché par ce chantier.
    expect(screen.queryByTestId('glass-blur')).toBeNull();
    expect(aplat(screen.getByTestId('glass-surface').props.style).backgroundColor).toBe('#ffffff');
  });

  it('floute ce qu’il y a derrière en mode sombre', () => {
    render(
      <GlassSurface>
        <Text>contenu</Text>
      </GlassSurface>,
    );

    expect(screen.getByTestId('glass-blur', MASQUE)).toBeTruthy();
  });

  it('pose un voile qui tient même sans flou', () => {
    render(
      <GlassSurface>
        <Text>contenu</Text>
      </GlassSurface>,
    );

    /*
     * Le voile est une couche SÉPARÉE du flou, et non une propriété du BlurView.
     *
     * Sur un appareil où le flou n'est pas rendu, un BlurView devient une vue transparente. Si le
     * voile vivait dedans, le panneau disparaîtrait : du texte clair sur un fond nuit, sans
     * cadre. Il vit donc à côté, et survit à l'absence du flou.
     */
    const voile = aplat(screen.getByTestId('glass-veil', MASQUE).props.style);

    expect(voile.backgroundColor).toMatch(/^rgba\(/);
  });

  it('éclaire son arête haute plus que sa base', () => {
    render(
      <GlassSurface>
        <Text>contenu</Text>
      </GlassSurface>,
    );

    // La lumière vient d'en haut. Une arête éclairée uniformément lit « rectangle avec bordure »,
    // pas « plaque de verre ».
    const haut = opacite(aplat(screen.getByTestId('glass-edge-top', MASQUE).props.style).backgroundColor);
    const bas = opacite(aplat(screen.getByTestId('glass-edge-bottom', MASQUE).props.style).backgroundColor);

    expect(haut).toBeGreaterThan(bas);
  });

  it('épaissit le voile quand on le demande', () => {
    const { rerender } = render(
      <GlassSurface>
        <Text>contenu</Text>
      </GlassSurface>,
    );
    const normal = opacite(aplat(screen.getByTestId('glass-veil', MASQUE).props.style).backgroundColor);

    rerender(
      <GlassSurface strong>
        <Text>contenu</Text>
      </GlassSurface>,
    );
    const fort = opacite(aplat(screen.getByTestId('glass-veil', MASQUE).props.style).backgroundColor);

    // `strong` sert aux surfaces qui portent du texte long : plus le voile est dense, plus le
    // contraste du texte est prévisible.
    expect(fort).toBeGreaterThan(normal);
  });

  it('rend ses enfants dans les deux modes', () => {
    render(
      <GlassSurface>
        <Text>contenu</Text>
      </GlassSurface>,
    );
    expect(screen.getByText('contenu')).toBeTruthy();

    mockScheme.colorScheme = 'light';
    render(
      <GlassSurface>
        <Text>contenu clair</Text>
      </GlassSurface>,
    );
    expect(screen.getByText('contenu clair')).toBeTruthy();
  });
});

/** Réduit un style React Native — objet, tableau, imbriqué — à un seul objet. */
function aplat(style: unknown): Record<string, unknown> {
  if (Array.isArray(style)) {
    return style.reduce<Record<string, unknown>>((acc, s) => ({ ...acc, ...aplat(s) }), {});
  }

  return (style ?? {}) as Record<string, unknown>;
}

/** Extrait l'alpha d'un `rgba(…)`. */
function opacite(couleur: unknown): number {
  const m = /rgba\([^,]+,[^,]+,[^,]+,\s*([\d.]+)\s*\)/.exec(String(couleur));

  if (!m) {
    throw new Error(`couleur sans alpha : ${String(couleur)}`);
  }

  return Number(m[1]);
}
