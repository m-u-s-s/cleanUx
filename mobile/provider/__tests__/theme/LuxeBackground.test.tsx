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

  /*
   * CE TEST DISAIT « ne rend rien en mode clair », ET LA DÉCISION A CHANGÉ.
   *
   * La raison d'origine reste vraie : un prestataire au soleil a besoin de contraste, pas de
   * translucidité. Le fond clair ne la contredit pas — trois auras très diffuses, AUCUNE
   * goutte, aucun mouvement, opacité plafonnée à 0,10.
   *
   * Ce qui l'a rendu nécessaire : sans quelque chose à filtrer, une surface de verre posée sur
   * un aplat uni est indiscernable d'une surface opaque. Tout le traitement disparaissait en
   * mode clair.
   */
  it('rend un fond sobre en mode clair', () => {
    mockScheme.colorScheme = 'light';

    render(<LuxeBackground />);

    expect(screen.getByTestId('luxe-background-clair', MASQUE)).toBeTruthy();
  });

  /** TÉMOIN — le fond clair n'est PAS le fond nuit : aucune goutte ne s'y invite. */
  it('le fond clair ne porte aucune goutte', () => {
    mockScheme.colorScheme = 'light';

    render(<LuxeBackground />);

    expect(screen.queryByTestId('luxe-background')).toBeNull();
  });

  /** Il reste décoratif : un lecteur d'écran ne doit pas l'annoncer avant chaque écran. */
  it('le fond clair reste invisible aux lecteurs d ecran', () => {
    mockScheme.colorScheme = 'light';

    render(<LuxeBackground />);

    const fond = screen.getByTestId('luxe-background-clair', MASQUE);

    expect(fond.props.accessibilityElementsHidden).toBe(true);
    expect(fond.props.importantForAccessibility).toBe('no-hide-descendants');
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
