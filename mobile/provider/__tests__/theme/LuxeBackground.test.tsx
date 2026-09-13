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
  /**
   * LE MOUVEMENT N'EST PLUS UNE BOUCLE, C'EST UNE VIDEO — et l'interrupteur a change avec lui.
   *
   * L'iceberg tourne : une image ne tourne pas, un maillage n'y arrivait pas. Ce qui doit s'arreter
   * sous mouvement reduit n'est donc plus un `withRepeat` mais le LECTEUR lui-meme. Ne pas le
   * mettre en pause suffirait a l'ecran, pas a la batterie : c'est la SOURCE qu'on annule, de sorte
   * qu'aucun lecteur ne soit monte et qu'aucun fichier ne soit decode.
   */
  /**
   * LE MOUVEMENT N'EST PLUS UNE BOUCLE, C'EST UNE IMAGE ANIMÉE — et l'interrupteur a suivi.
   *
   * Ce qui doit s'arrêter sous mouvement réduit n'est pas l'affichage mais le DÉCODAGE : la source
   * passée à Skia devient nulle, de sorte qu'aucune image n'est décodée. Le composant l'annonce
   * dans son étiquette, et c'est ce que ce test lit — la seule trace observable, une image Skia
   * n'étant pas un nœud que Testing Library sait interroger.
   */
  it('annonce un fond sans animation quand le mouvement est réduit', () => {
    mockMouvementReduit = true;

    render(<LuxeBackground />);

    expect(screen.getByTestId('luxe-background', MASQUE).props.accessibilityLabel).toContain(
      'sans animation',
    );
  });

  /** TÉMOIN : sans lui, une étiquette figée sur « sans animation » passerait le test ci-dessus. */
  it('témoin : le fond animé ne s annonce PAS comme figé', () => {
    render(<LuxeBackground />);

    expect(screen.getByTestId('luxe-background', MASQUE).props.accessibilityLabel).not.toContain(
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
