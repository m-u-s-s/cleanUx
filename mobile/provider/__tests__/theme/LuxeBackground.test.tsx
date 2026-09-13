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

/*
 * On garde tout Reanimated, on n'espionne que `withRepeat` : c'est le seul appel qui décide si
 * une boucle infinie démarre. Le remplacer par un faux complet ferait échouer le rendu, et
 * mesurer l'absence de boucle sur un composant qui ne rend pas ne prouverait rien.
 */
jest.mock('react-native-reanimated', () => {
  const vrai = jest.requireActual('react-native-reanimated');

  return { ...vrai, withRepeat: jest.fn((...args: unknown[]) => vrai.withRepeat(...args)) };
});

import { withRepeat } from 'react-native-reanimated';

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
    (withRepeat as jest.Mock).mockClear();
  });

  /*
   * CE TEST A CHANGÉ DEUX FOIS. Il disait « ne rend rien en mode clair », puis « rend un fond
   * sobre, sans mouvement ». Avec « Verre givré », le maillage clair dérive lui aussi.
   *
   * Ce qui reste vrai à chaque révision : sans quelque chose à filtrer, une plaque de verre
   * posée sur un aplat uni est indiscernable d'une plaque opaque. Le fond clair n'est pas une
   * décoration, c'est ce qui rend le verre visible.
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

  /**
   * L'ÉTIQUETTE NE PROUVE RIEN — c'est la boucle qu'il faut mesurer.
   *
   * Le test au-dessus vérifie ce que le fond ANNONCE. Un fond qui annonce « sans animation » et
   * continue de tourner passerait au vert, en consommant la batterie d'un prestataire toute la
   * journée. Ici on regarde si la boucle infinie est seulement lancée.
   */
  it('ne lance AUCUNE boucle quand le mouvement est réduit', () => {
    mockMouvementReduit = true;

    render(<LuxeBackground />);

    expect(withRepeat).not.toHaveBeenCalled();
  });

  /** TÉMOIN : sans lui, un `withRepeat` cassé rendrait le test précédent vert pour rien. */
  it('témoin : la boucle est bien lancée quand le mouvement est permis', () => {
    render(<LuxeBackground />);

    expect(withRepeat).toHaveBeenCalled();

    /*
     * SANS FIN, ET EN ALLER-RETOUR. C'était l'inverse tant que le fond était un maillage : il
     * TOURNAIT, et une phase qui revient sur ses pas aurait fait tourner l'objet à l'envers une
     * fois sur deux. Le fond est maintenant le rendu de la planche, qui ne tourne pas — il
     * respire. Une respiration qui repartirait de zéro se verrait sauter à chaque cycle.
     */
    const [, repetitions, allerRetour] = (withRepeat as jest.Mock).mock.calls[0] ?? [];

    expect(repetitions).toBe(-1);
    expect(allerRetour).toBe(true);
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
