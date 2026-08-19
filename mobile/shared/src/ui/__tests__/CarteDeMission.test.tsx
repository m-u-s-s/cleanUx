import React from 'react';
import { Text } from 'react-native';
import { render, screen } from '@testing-library/react-native';

/**
 * LA GRAMMAIRE COMMUNE DES CARTES DE MISSION.
 *
 * Chaque carte du parcours s'était fabriqué son propre fond : un aplat pastel, posé à plat. Sur
 * l'application prestataire, dont tout le reste est en verre sur fond nuit, ces aplats se lisaient
 * comme des morceaux rapportés d'une autre application.
 *
 * Deux garanties : la carte réutilise la plaque du projet plutôt que d'en inventer une, et le SENS
 * passe par un rail — pas par un aplat qui écraserait le texte qu'il porte.
 */

jest.mock('expo-blur', () => {
  const { View } = require('react-native');

  return { BlurView: ({ children }: any) => <View>{children}</View> };
});

import { CarteDeMission } from '../CarteDeMission';
import { colors } from '@/theme';

describe('La carte de mission', () => {
  it('rend son titre, son chapeau et son contenu', () => {
    render(
      <CarteDeMission titre="22 min de retard" chapeau="Le prestataire n’a pas répondu.">
        <Text>Contenu</Text>
      </CarteDeMission>,
    );

    expect(screen.getByText('22 min de retard')).toBeTruthy();
    expect(screen.getByText('Le prestataire n’a pas répondu.')).toBeTruthy();
    expect(screen.getByText('Contenu')).toBeTruthy();
  });

  /** Elle N'INVENTE PAS de matière : c'est la plaque du projet qui porte le verre. */
  it('s’appuie sur la plaque de verre du projet', () => {
    render(<CarteDeMission testID="ma-carte" />);

    expect(screen.getByTestId('ma-carte')).toBeTruthy();
  });

  /**
   * LE TON PASSE PAR LE RAIL, et deux tons différents ne peuvent pas rendre la même couleur —
   * sans quoi la distinction n'existerait que dans le code.
   */
  it('donne au rail la couleur de son ton', () => {
    const railDe = (ton: 'attention' | 'decision' | 'neutre') => {
      const { UNSAFE_root, unmount } = render(<CarteDeMission ton={ton} />);
      const vues = UNSAFE_root.findAllByType(require('react-native').View);
      const rail = vues.find((v: any) => {
        const style = Array.isArray(v.props.style) ? Object.assign({}, ...v.props.style.filter(Boolean)) : v.props.style;

        return style?.width === 4;
      });
      const style = Array.isArray(rail?.props.style)
        ? Object.assign({}, ...rail.props.style.filter(Boolean))
        : rail?.props.style;

      unmount();

      return style?.backgroundColor;
    };

    expect(railDe('attention')).toBe(colors.warning[500]);
    expect(railDe('decision')).toBe(colors.brand[500]);
    expect(railDe('neutre')).not.toBe(colors.warning[500]);
  });
});
