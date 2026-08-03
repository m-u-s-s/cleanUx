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
import { apparenceDeBarre } from '@/ui/glassBars';

describe('apparenceDeBarre', () => {
  beforeEach(() => {
    mockScheme.colorScheme = 'dark';
  });

  it('efface la barre en mode sombre pour laisser passer le fond', () => {
    const { tabBarStyle } = apparenceDeBarre(jetons());

    // Transparente ET sans liseré : un `borderTopWidth` oublié suffit à tracer la ligne qu'on
    // cherche justement à supprimer.
    expect(tabBarStyle.backgroundColor).toBe('transparent');
    expect(tabBarStyle.borderTopWidth).toBe(0);
  });

  it('pose une plaque de verre derrière la barre en mode sombre', () => {
    const { tabBarBackground } = apparenceDeBarre(jetons());

    expect(tabBarBackground).toBeDefined();

    render(<>{tabBarBackground?.()}</>);

    expect(screen.getByTestId('glass-bar', { includeHiddenElements: true })).toBeTruthy();
  });

  it('garde en clair exactement la barre d’avant', () => {
    mockScheme.colorScheme = 'light';
    const theme = jetons();
    const { tabBarStyle, tabBarBackground } = apparenceDeBarre(theme);

    // Le mode clair n'est pas touché : fond plein, liseré du thème, aucune plaque.
    expect(tabBarStyle.backgroundColor).toBe(theme.bg);
    expect(tabBarStyle.borderTopColor).toBe(theme.border);
    expect(tabBarBackground).toBeUndefined();
  });

  it('donne une plaque à angles droits', () => {
    const { tabBarBackground } = apparenceDeBarre(jetons());

    render(<>{tabBarBackground?.()}</>);

    /*
     * Une barre d'onglets touche les trois bords de l'écran. Le rayon par défaut de GlassSurface
     * (20) arrondirait ses coins bas, laissant deux encoches sur le fond nuit.
     */
    const style = aplat(screen.getByTestId('glass-bar', { includeHiddenElements: true }).props.style);

    expect(style.borderRadius).toBe(0);
  });
});

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
