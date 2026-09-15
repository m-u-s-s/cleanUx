import React from 'react';
import { fireEvent, render, within } from '@testing-library/react-native';

// Préfixé `mock` : la règle de survol de Jest autorise une variable ainsi nommée à traverser dans
// une fabrique `jest.mock`, alors que l'appel est hissé au-dessus des imports.
let mockReduit = false;

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    GlassSurface: ({ children, testID, style }: any) => <View testID={testID} style={style}>{children}</View>,
    Icon: ({ name }: any) => <Text>{name}</Text>,
    Button: ({ label, onPress, testID }: any) => <Text onPress={onPress} testID={testID}>{label}</Text>,
    useReducedMotion: () => mockReduit,
  };
});

/*
 * Le double `react-native-reanimated` du dépôt (`mobile/client/__mocks__/react-native-reanimated.js`,
 * substitué à tout le paquet par `jest.config.ts`) fait de `withRepeat` une fonction identité. Un
 * `jest.spyOn` posé APRÈS l'import ne voit jamais les appels du composant : celui-ci mélange un
 * import par défaut et des imports nommés (`import Animated, { withRepeat, ... }`), ce que Babel
 * compile en une copie de l'export prise au chargement du module — avant qu'un espion tardif ne
 * puisse s'y poser (mesuré : le témoin positif ci-dessous échouait avec cette approche). En
 * enveloppant `withRepeat` ICI, dans la fabrique du mock — hissée par Jest au-dessus de tous les
 * imports — le composant reçoit directement la fonction espionne dès son tout premier import.
 */
jest.mock('react-native-reanimated', () => {
  const reel = jest.requireActual('react-native-reanimated');
  return { ...reel, withRepeat: jest.fn(reel.withRepeat) };
});

import { construireLaSonde } from '@/catalogue';
import { RepereDeSonde } from '@/screens/catalogue/RepereDeSonde';
import { withRepeat } from 'react-native-reanimated';

const metier = (slug: string, name: string) => ({
  slug, name, icon: null, short_description: null, floor_price_cents: 12000, hourly: false,
});

// `construireLaSonde` retourne `RepereDeSonde[]` : sous `noUncheckedIndexedAccess`, la
// déstructuration comme l'indexation rendent `RepereDeSonde | undefined`. Le `!` est sûr ici — le
// tableau ci-dessus a bien trois repères — et suit le précédent de `modeleDeSonde.test.ts`.
const sonde = construireLaSonde([
  { slug: 'batiment', name: 'Bâtiment', icon: 'hammer', trades: [metier('peinture', 'Peinture'), metier('plomberie', 'Plomberie')] },
  { slug: 'nettoyage', name: 'Nettoyage', icon: 'sparkles', trades: [metier('vitres', 'Vitres')] },
]);
const peinture = sonde[0]!;
const plomberie = sonde[1]!;
const vitres = sonde[2]!;

const afficher = (repere = peinture, choisi = false, onChoisir = jest.fn()) =>
  render(
    <RepereDeSonde repere={repere} choisi={choisi} libellePrix="dès 120 € hors taxe" onChoisir={onChoisir} />,
  );

beforeEach(() => {
  mockReduit = false;
  (withRepeat as jest.Mock).mockClear();
});

describe('RepereDeSonde', () => {
  it('pose la case du premier secteur à droite de la ligne', () => {
    const ecran = afficher(peinture);
    expect(within(ecran.getByTestId('moitie-droite-peinture')).getByTestId('case-peinture')).toBeTruthy();
    expect(within(ecran.getByTestId('moitie-gauche-peinture')).queryByTestId('case-peinture')).toBeNull();
  });

  it('pose la case du secteur suivant à gauche', () => {
    const ecran = afficher(vitres);
    expect(within(ecran.getByTestId('moitie-gauche-vitres')).getByTestId('case-vitres')).toBeTruthy();
  });

  it("met l'étiquette du secteur en face de ses cases", () => {
    const ecran = afficher(peinture);
    expect(within(ecran.getByTestId('moitie-gauche-peinture')).getByTestId('etiquette-peinture')).toBeTruthy();
  });

  it("témoin : le second métier du secteur n'a pas d'étiquette", () => {
    expect(afficher(plomberie).queryByTestId('etiquette-plomberie')).toBeNull();
  });

  it('se présente comme un bouton sélectionnable, avec son prix', () => {
    const repere = afficher(peinture, true).getByTestId('repere-peinture');
    expect(repere.props.accessibilityRole).toBe('button');
    expect(repere.props.accessibilityState).toEqual({ selected: true });
    expect(repere.props.accessibilityLabel).toBe('Peinture, dès 120 € hors taxe');
  });

  it('témoin : un repère non choisi le dit aussi', () => {
    expect(afficher(peinture, false).getByTestId('repere-peinture').props.accessibilityState).toEqual({ selected: false });
  });

  it('se choisit au toucher', () => {
    const onChoisir = jest.fn();
    fireEvent.press(afficher(peinture, false, onChoisir).getByTestId('repere-peinture'));
    expect(onChoisir).toHaveBeenCalledTimes(1);
  });

  it('le halo respire autour du repère choisi', () => {
    mockReduit = false;

    afficher(peinture, true);

    expect(withRepeat).toHaveBeenCalled();
  });

  it('témoin inverse : en mouvement réduit, le halo ne respire pas', () => {
    mockReduit = true;

    afficher(peinture, true);

    expect(withRepeat).not.toHaveBeenCalled();
  });
});
