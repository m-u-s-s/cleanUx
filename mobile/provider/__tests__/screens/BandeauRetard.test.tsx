import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { BandeauRetard } from '@/screens/components/BandeauRetard';

/**
 * LE RETARD, VU DU PRESTATAIRE.
 *
 * Ce qu'il ignore n'est pas l'heure — il a une montre. C'est que la plateforme a DÉJÀ prévenu son
 * client. Le bandeau porte donc deux informations que lui seul n'a pas, et un geste : annoncer une
 * heure d'arrivée, la seule chose qui évite l'annulation gratuite.
 */

let mockRetard: unknown = null;
const mockAnnoncer = jest.fn();

jest.mock('@/missions', () => ({
  useMonRetard: () => ({ data: mockRetard }),
  useAnnoncerMonRetard: () => ({ mutate: mockAnnoncer, isPending: false }),
}));

jest.mock('@/ui', () => {
  const { Text, TouchableOpacity, TextInput: RNTextInput } = require('react-native');

  return {
    Button: ({ label, onPress, testID }: any) => (
      <TouchableOpacity onPress={onPress} testID={testID}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    TextInput: ({ value, onChangeText, testID }: any) => (
      <RNTextInput value={value} onChangeText={onChangeText} testID={testID} />
    ),
  };
});

const EN_RETARD = {
  en_retard: true,
  minutes: 22,
  heure_prevue: '2026-08-19T14:00:00+00:00',
  annonce: null,
  annulation_gratuite: true,
  prevenu_at: '2026-08-19T14:16:00+00:00',
};

describe('Mon retard, côté prestataire', () => {
  beforeEach(() => {
    mockRetard = null;
    jest.clearAllMocks();
  });

  it('ne s’affiche pas quand on est à l’heure', () => {
    mockRetard = { ...EN_RETARD, en_retard: false, minutes: null };

    render(<BandeauRetard missionId={12} />);

    expect(screen.queryByTestId('bandeau-retard')).toBeNull();
  });

  /* CE QUE LE CLIENT SAIT DÉJÀ est l'information qui manque vraiment. */
  it('dit que le client a été prévenu, et qu’il peut annuler', () => {
    mockRetard = EN_RETARD;

    render(<BandeauRetard missionId={12} />);

    expect(screen.getByText('22 min de retard')).toBeTruthy();
    expect(screen.getByText(/Le client a été prévenu/)).toBeTruthy();
    expect(screen.getByText(/annuler sans frais/)).toBeTruthy();
  });

  it('annonce une arrivée avec son motif', () => {
    mockRetard = EN_RETARD;

    render(<BandeauRetard missionId={12} />);

    fireEvent.changeText(screen.getByTestId('retard-motif'), 'Embouteillage');
    fireEvent.press(screen.getByTestId('retard-annoncer-20'));

    expect(mockAnnoncer).toHaveBeenCalledWith(
      { minutes: 20, reason: 'Embouteillage' },
      expect.anything(),
    );
  });

  it('rappelle l’heure déjà annoncée', () => {
    mockRetard = {
      ...EN_RETARD,
      annonce: { arrivee_at: '2026-08-19T14:35:00+00:00', motif: null },
    };

    render(<BandeauRetard missionId={12} />);

    expect(screen.getByTestId('retard-deja-annonce')).toBeTruthy();
  });
});
