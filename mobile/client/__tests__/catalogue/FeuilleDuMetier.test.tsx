import React from 'react';
import { StyleSheet } from 'react-native';
import { fireEvent, render } from '@testing-library/react-native';
import { spacing } from '@/theme';

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    GlassSurface: ({ children, testID, style }: any) => <View testID={testID} style={style}>{children}</View>,
    Icon: ({ name }: any) => <Text testID="feuille-icone">{name}</Text>,
    Button: ({ label, onPress, testID }: any) => <Text onPress={onPress} testID={testID}>{label}</Text>,
  };
});

import { construireLaSonde } from '@/catalogue';
import { FeuilleDuMetier } from '@/screens/catalogue/FeuilleDuMetier';

const sonde = construireLaSonde([
  {
    slug: 'batiment', name: 'Bâtiment', icon: 'hammer',
    trades: [
      { slug: 'peinture', name: 'Peinture', icon: 'paint-roller', short_description: null, floor_price_cents: 12000, hourly: false },
      { slug: 'plomberie', name: 'Plomberie', icon: 'wrench', short_description: 'Fuites, débouchages', floor_price_cents: 8500, hourly: false },
    ],
  },
  { slug: 'nettoyage', name: 'Nettoyage', icon: 'sparkles', trades: [
    { slug: 'vitres', name: 'Vitres', icon: 'window', short_description: null, floor_price_cents: null, hourly: false },
  ] },
]);

describe('FeuilleDuMetier', () => {
  it('dit le métier, sa place dans la liste et son prix', () => {
    const ecran = render(<FeuilleDuMetier repere={sonde[1]!} libellePrix="dès 85 € hors taxe" onCommander={jest.fn()} />);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Plomberie');
    expect(ecran.getByTestId('feuille-position').props.children).toBe('Bâtiment · 2 sur 3');
    expect(ecran.getByTestId('feuille-prix').props.children).toBe('dès 85 € hors taxe');
    expect(ecran.getByTestId('feuille-icone').props.children).toBe('build-outline');
    expect(ecran.getByText('Fuites, débouchages')).toBeTruthy();
  });

  it('témoin : sans description, aucune ligne vide', () => {
    const ecran = render(<FeuilleDuMetier repere={sonde[0]!} libellePrix="dès 120 € hors taxe" onCommander={jest.fn()} />);
    expect(ecran.queryByText('Fuites, débouchages')).toBeNull();
  });

  it('commande au toucher du bouton', () => {
    const onCommander = jest.fn();
    const ecran = render(<FeuilleDuMetier repere={sonde[2]!} libellePrix="Prix selon vos réponses" onCommander={onCommander} />);

    fireEvent.press(ecran.getByTestId('feuille-commander'));

    expect(onCommander).toHaveBeenCalledTimes(1);
    expect(ecran.getByTestId('feuille-commander').props.children).toBe('Commander');
  });

  it('la feuille laisse la place de la barre du bas de l’appareil', () => {
    // Sous jest, `react-native-safe-area-context` est substitué par le double du dépôt
    // (jest.config.ts:83), dont les marges valent toutes 0 (mock, ligne 4). On force ici une
    // marge basse non nulle pour prouver qu'elle s'ajoute bien au padding de la feuille.
    const espion = jest
      .spyOn(require('react-native-safe-area-context'), 'useSafeAreaInsets')
      .mockReturnValue({ top: 0, right: 0, bottom: 34, left: 0 });

    const ecran = render(<FeuilleDuMetier repere={sonde[0]!} libellePrix="dès 120 € hors taxe" onCommander={jest.fn()} />);
    const style = StyleSheet.flatten(ecran.getByTestId('feuille-du-metier').props.style);

    expect(style.paddingBottom).toBe(spacing.md + 34);

    espion.mockRestore();
  });
});
