/**
 * Le fond nuit — dégradé, lueur de marque, gouttes.
 *
 * Ce que ces tests peuvent prouver : qu'il ne s'affiche qu'en sombre, qu'il respecte le mouvement
 * réduit, et qu'il retombe sans rien déplacer quand le rendu riche est indisponible.
 *
 * Ce qu'ils NE peuvent pas prouver : que les gouttes soient belles. Skia s'installe par des
 * liaisons natives absentes en environnement Jest — le rendu réel se vérifie sur un development
 * build, jamais ici.
 */
import React from 'react';
import { render, screen } from '@testing-library/react-native';

const mockScheme = { colorScheme: 'dark' as 'dark' | 'light', mode: 'dark', setMode: jest.fn() };
// Préfixe `mock` exigé par Jest : seules ces variables peuvent être lues depuis une
// fabrique de mock, qui est hissée avant les déclarations du fichier.
let mockMouvementReduit = false;

jest.mock('@/theme/useColorScheme', () => ({ useColorScheme: () => mockScheme }));
jest.mock('@/ui/a11y', () => ({
  useReducedMotion: () => mockMouvementReduit,
  useScreenReader: () => false,
  a11y: {},
}));

import { LuxeBackground } from '@/ui/LuxeBackground';

/**
 * Testing Library exclut par défaut ce qui est masqué aux lecteurs d'écran. Le fond l'est
 * délibérément — il faut donc le demander explicitement pour l'atteindre. Cette option n'est pas
 * une commodité : c'est la contrepartie du test « décoratif » ci-dessous.
 */
const MASQUE = { includeHiddenElements: true } as const;

describe('LuxeBackground', () => {
  beforeEach(() => {
    mockScheme.colorScheme = 'dark';
    mockMouvementReduit = false;
  });

  it('ne rend rien en mode clair', () => {
    mockScheme.colorScheme = 'light';

    const { toJSON } = render(<LuxeBackground />);

    // Le luxe est un traitement du SOMBRE. En clair, un prestataire au soleil a besoin de
    // contraste, pas de translucidité.
    expect(toJSON()).toBeNull();
  });

  it('rend le fond en mode sombre', () => {
    render(<LuxeBackground />);

    expect(screen.getByTestId('luxe-background', MASQUE)).toBeTruthy();
  });

  it('annonce qu’il est décoratif aux lecteurs d’écran', () => {
    render(<LuxeBackground />);

    // Un fond n'a rien à dire : le laisser accessible ferait annoncer « image » avant chaque
    // écran, sans qu'aucune information ne suive. La requête SANS l'option ne doit donc rien
    // trouver — c'est exactement ce que verrait VoiceOver.
    expect(screen.queryByTestId('luxe-background')).toBeNull();
    expect(screen.getByTestId('luxe-background', MASQUE).props.accessibilityElementsHidden).toBe(
      true,
    );
  });

  it('fige les gouttes quand le système demande un mouvement réduit', () => {
    mockMouvementReduit = true;

    render(<LuxeBackground />);

    expect(screen.getByTestId('luxe-background', MASQUE).props.accessibilityLabel).toContain(
      'sans animation',
    );
  });

  it('garde le même point de montage quel que soit le rendu', () => {
    // Le repli ne doit RIEN déplacer : même testID, mêmes dimensions. Seule la matière change,
    // jamais la structure de l'écran qui le contient.
    const { rerender } = render(<LuxeBackground />);
    const avant = screen.getByTestId('luxe-background', MASQUE).props.style;

    mockMouvementReduit = true;
    rerender(<LuxeBackground />);

    expect(screen.getByTestId('luxe-background', MASQUE).props.style).toEqual(avant);
  });
});
