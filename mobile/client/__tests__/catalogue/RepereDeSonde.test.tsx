import React from 'react';
import { fireEvent, render, within } from '@testing-library/react-native';

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    GlassSurface: ({ children, testID, style }: any) => <View testID={testID} style={style}>{children}</View>,
    Icon: ({ name }: any) => <Text>{name}</Text>,
    Button: ({ label, onPress, testID }: any) => <Text onPress={onPress} testID={testID}>{label}</Text>,
  };
});

import { construireLaSonde } from '@/catalogue';
import { RepereDeSonde } from '@/screens/catalogue/RepereDeSonde';

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
    <RepereDeSonde repere={repere} choisi={choisi} libellePrix="dès 120 € hors taxe" mouvementReduit={false} onChoisir={onChoisir} />,
  );

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
});
