import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { MissionSheet } from '@/screens/components/MissionSheet';

/**
 * LA FEUILLE « MA MISSION » — l'aperçu, et le second maillon de la porte d'entrée.
 *
 * Le premier maillon — le bouton sous la carte — est couvert par `SurPlace.test.tsx`. Celui-ci
 * vérifie que la feuille ANNONCE ce qui attend et CONDUIT là où il faut : une feuille qui ne dit
 * rien se referme sans être lue, et le prestataire attend devant le client pour rien.
 */

let mockSupplements: unknown[] = [];
let mockRevision: unknown = null;
let mockTaches: unknown[] = [];

/*
 * LE BOUTON D'APPEL EST BOUCHONNÉ : il interroge le serveur pour son numéro relais, et ce test-ci
 * porte sur ce que la feuille ANNONCE, pas sur la téléphonie. Son propre comportement — le message
 * quand la ligne est fermée — se teste là où il vit.
 */
jest.mock('@brio/shared', () => {
  const { Text, TouchableOpacity } = require('react-native');

  return {
    BoutonAppelMasque: ({ testID }: any) => (
      <TouchableOpacity testID={testID}>
        <Text>Appeler</Text>
      </TouchableOpacity>
    ),
  };
});

jest.mock('@/booking/onsite', () => ({
  useOnSiteTimeline: () => ({ data: { progress: { done: 1, total: 3, percent: 33 } } }),
  useOnSiteExtras: () => ({ data: mockSupplements }),
  useRevisionDeDevis: () => ({ data: mockRevision }),
  useTodoList: () => ({ data: { engine: 'domicile', window: { open: true }, items: mockTaches, suggestions: [] } }),
}));

// La feuille de `@gorhom/bottom-sheet` rend ses enfants derrière une animation ; on la réduit à une
// vue pour que le test porte sur le CONTENU, pas sur le ressort.
jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity } = require('react-native');
  const ReactLocal = require('react');

  return {
    BottomSheet: ReactLocal.forwardRef(({ children }: any, _ref: unknown) => <View>{children}</View>),
    Button: ({ label, onPress, testID }: any) => (
      <TouchableOpacity onPress={onPress} testID={testID}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    Icon: () => <View />,
    Badge: ({ label }: any) => <Text>{label}</Text>,
  };
});

describe('Feuille « Ma mission »', () => {
  beforeEach(() => {
    mockSupplements = [];
    mockRevision = null;
    mockTaches = [];
  });

  const rendre = (rappels: Partial<Record<'onGerer' | 'onMessage' | 'onLitige', () => void>> = {}) =>
    render(
      <MissionSheet
        bookingId={77}
        onGerer={rappels.onGerer ?? jest.fn()}
        onMessage={rappels.onMessage ?? jest.fn()}
        onLitige={rappels.onLitige ?? jest.fn()}
      />,
    );

  it('conduit à « Gérer ma mission »', () => {
    const onGerer = jest.fn();
    rendre({ onGerer });

    fireEvent.press(screen.getByTestId('ouvrir-gerer-ma-mission'));

    expect(onGerer).toHaveBeenCalled();
  });

  /** LE TÉMOIN : sans rien en attente, la feuille ne crie pas au loup. */
  it('n’annonce rien quand rien n’attend', () => {
    rendre();

    expect(screen.queryByTestId('mission-sheet-attente')).toBeNull();
  });

  it('annonce un devis révisé en attente', () => {
    mockRevision = { id: 9, awaiting_client: true };
    rendre();

    expect(screen.getByText('1 chose attend votre réponse')).toBeTruthy();
  });

  it('additionne le devis et les suppléments en attente', () => {
    mockRevision = { id: 9, awaiting_client: true };
    mockSupplements = [{ id: 1, awaiting_client: true }, { id: 2, awaiting_client: false }];
    rendre();

    expect(screen.getByText('2 choses attendent votre réponse')).toBeTruthy();
  });

  it('ouvre la messagerie et le litige', () => {
    const onMessage = jest.fn();
    const onLitige = jest.fn();
    rendre({ onMessage, onLitige });

    fireEvent.press(screen.getByTestId('mission-sheet-message'));
    fireEvent.press(screen.getByTestId('mission-sheet-litige'));

    expect(onMessage).toHaveBeenCalled();
    expect(onLitige).toHaveBeenCalled();
  });

  it('dit combien de tâches du client restent à faire', () => {
    mockTaches = [{ id: 1, done: false }, { id: 2, done: true }];
    rendre();

    expect(screen.getByText('1 tâche de votre liste reste à faire')).toBeTruthy();
  });
});
