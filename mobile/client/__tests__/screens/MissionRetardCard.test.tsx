import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { MissionRetardCard } from '@/screens/components/MissionRetardCard';

/**
 * LE RETARD, DIT PAR LA PLATEFORME.
 *
 * Trois garanties :
 *
 *   - à l'heure, la carte n'existe pas — un encart « 0 min de retard » inquiéterait pour rien ;
 *   - l'absence de réponse du prestataire SE DIT, au lieu de laisser une ligne vide qui donnerait
 *     l'impression qu'on n'a rien demandé ;
 *   - le bouton d'annulation ne promet la gratuité que si le serveur la donne.
 */

let mockRetard: unknown = null;
const mockReprogrammer = jest.fn();

jest.mock('@/booking/onsite', () => ({
  useRetard: () => ({ data: mockRetard }),
  useReprogrammer: () => ({ mutate: mockReprogrammer, isPending: false }),
}));

jest.mock('@/ui', () => {
  const { Text, TouchableOpacity } = require('react-native');

  return {
    Button: ({ label, onPress, testID }: any) => (
      <TouchableOpacity onPress={onPress} testID={testID}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
  };
});

const EN_RETARD = {
  en_retard: true,
  minutes: 22,
  heure_prevue: '2026-08-19T14:00:00+00:00',
  annonce: null,
  annulation_gratuite: false,
  prevenu_at: '2026-08-19T14:16:00+00:00',
};

describe('Le retard du prestataire, côté client', () => {
  beforeEach(() => {
    mockRetard = null;
    jest.clearAllMocks();
  });

  it('ne s’affiche pas quand tout va bien', () => {
    mockRetard = { ...EN_RETARD, en_retard: false, minutes: null };

    render(<MissionRetardCard bookingId={7} />);

    expect(screen.queryByTestId('retard-prestataire')).toBeNull();
  });

  it('dit de combien, et que personne n’a répondu', () => {
    mockRetard = EN_RETARD;

    render(<MissionRetardCard bookingId={7} />);

    expect(screen.getByText('22 min de retard')).toBeTruthy();
    expect(screen.getByText('Le prestataire n’a pas encore répondu.')).toBeTruthy();
  });

  it('reprend l’heure annoncée et son motif', () => {
    mockRetard = {
      ...EN_RETARD,
      annonce: { arrivee_at: '2026-08-19T14:35:00+00:00', motif: 'Embouteillage' },
    };

    render(<MissionRetardCard bookingId={7} />);

    expect(screen.getByText(/Embouteillage/)).toBeTruthy();
  });

  /* DÉCALER EST UNE VRAIE ACTION : on presse, on ne se contente pas de voir le bouton. */
  it('décale l’intervention quand on le demande', () => {
    mockRetard = EN_RETARD;

    render(<MissionRetardCard bookingId={7} />);
    fireEvent.press(screen.getByTestId('retard-decaler-demain'));

    expect(mockReprogrammer).toHaveBeenCalledTimes(1);
  });

  it('ne promet la gratuité que si le serveur la donne', () => {
    mockRetard = EN_RETARD;
    const { rerender } = render(<MissionRetardCard bookingId={7} onAnnuler={jest.fn()} />);

    expect(screen.getByText('Annuler l’intervention')).toBeTruthy();

    mockRetard = { ...EN_RETARD, annulation_gratuite: true };
    rerender(<MissionRetardCard bookingId={7} onAnnuler={jest.fn()} />);

    expect(screen.getByText('Annuler sans frais')).toBeTruthy();
  });
});
