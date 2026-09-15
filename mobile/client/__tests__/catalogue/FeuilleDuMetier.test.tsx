import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

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

/**
 * `useSafeAreaInsets` exige un `SafeAreaProvider` monté au-dessus — `App.tsx` en pose un en vrai,
 * aucun test du dépôt ne le simule et le préréglage `jest-expo` non plus. On rend donc sous le
 * vrai fournisseur, avec des métriques figées, plutôt que de contourner le composant.
 */
const afficherFeuille = (ui: React.ReactElement) =>
  render(
    <SafeAreaProvider
      initialMetrics={{ frame: { x: 0, y: 0, width: 390, height: 844 }, insets: { top: 0, left: 0, right: 0, bottom: 0 } }}
    >
      {ui}
    </SafeAreaProvider>,
  );

describe('FeuilleDuMetier', () => {
  it('dit le métier, sa place dans la liste et son prix', () => {
    const ecran = afficherFeuille(<FeuilleDuMetier repere={sonde[1]!} libellePrix="dès 85 € hors taxe" onCommander={jest.fn()} />);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Plomberie');
    expect(ecran.getByTestId('feuille-position').props.children).toBe('Bâtiment · 2 sur 3');
    expect(ecran.getByTestId('feuille-prix').props.children).toBe('dès 85 € hors taxe');
    expect(ecran.getByTestId('feuille-icone').props.children).toBe('build-outline');
    expect(ecran.getByText('Fuites, débouchages')).toBeTruthy();
  });

  it('témoin : sans description, aucune ligne vide', () => {
    const ecran = afficherFeuille(<FeuilleDuMetier repere={sonde[0]!} libellePrix="dès 120 € hors taxe" onCommander={jest.fn()} />);
    expect(ecran.queryByText('Fuites, débouchages')).toBeNull();
  });

  it('commande au toucher du bouton', () => {
    const onCommander = jest.fn();
    const ecran = afficherFeuille(<FeuilleDuMetier repere={sonde[2]!} libellePrix="Prix selon vos réponses" onCommander={onCommander} />);

    fireEvent.press(ecran.getByTestId('feuille-commander'));

    expect(onCommander).toHaveBeenCalledTimes(1);
    expect(ecran.getByTestId('feuille-commander').props.children).toBe('Commander');
  });
});
