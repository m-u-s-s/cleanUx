/**
 * LE CODE DE FIN, DANS « MES RENDEZ-VOUS ».
 *
 * Le prestataire ne peut pas clôturer sans six chiffres que le client seul détient. Ils
 * n'existaient nulle part dans cet écran : le client devait espérer un SMS, que le plafond de cinq
 * envois par heure et par numéro bloque justement au moment où l'on en a besoin.
 *
 * LA CARTE NE SE FIE PAS AU STATUT DE LA RÉSERVATION, et c'est le point délicat : celle-ci reste
 * `confirmed` pendant toute la mission — `isInProgress()` ne devient vrai que sur `en_route` ou
 * `sur_place`, que la clôture de mission n'écrit pas. Une garde sur ce statut n'aurait donc jamais
 * laissé la carte s'afficher. C'est le serveur qui arbitre, et il refuse proprement.
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
}));

// La réservation est déclarée DANS la fabrique : `jest.mock` est remonté au-dessus des `const`,
// et référencer une variable extérieure fait échouer la transformation.
jest.mock('@/booking', () => ({
  useBookingDetail: () => ({
    data: {
      id: 8,
      status: 'confirmed',
      state: 'confirmed',
      service_name: 'Nettoyage domicile',
      scheduled_date: '2026-08-13',
      scheduled_time: '10:00',
      address: 'Rue de Test 1',
      city: 'Bruxelles',
      provider_name: 'Karim B.',
      estimated_price: 188.5,
      contract_covered: false,
    },
    isLoading: false,
    isError: false,
    refetch: jest.fn(),
  }),
}));

const mockMutateCode = jest.fn();
jest.mock('@/tracking', () => ({
  useCompletionCode: () => ({ mutate: mockMutateCode, isPending: false }),
}));

import { BookingDetailScreen } from '@/screens/BookingDetailScreen';

function rendre() {
  return render(
    <BookingDetailScreen
      route={{ params: { bookingId: 8 } } as never}
      navigation={{} as never}
    />,
  );
}

beforeEach(() => {
  mockMutateCode.mockReset();
});

describe('BookingDetailScreen — code de fin', () => {
  it('exports without crash', () => {
    expect(BookingDetailScreen).toBeDefined();
  });

  it('propose le code de fin sur une réservation encore en cours', () => {
    rendre();

    expect(screen.getByTestId('carte-code-de-fin')).toBeTruthy();
    expect(screen.getByLabelText('Afficher mon code de fin')).toBeTruthy();
  });

  it('affiche les six chiffres rendus par le serveur', async () => {
    mockMutateCode.mockImplementation((_v: unknown, opts: any) =>
      opts.onSuccess({ mission_id: 12, code: '482951', expires_at: '2026-08-13T11:30:00+00:00' }),
    );

    rendre();
    fireEvent.press(screen.getByLabelText('Afficher mon code de fin'));

    await waitFor(() => expect(screen.getByText('482951')).toBeTruthy());
  });

  /** Le refus du serveur est repris tel quel : lui seul sait que la mission n'a pas démarré. */
  it('affiche le refus du serveur au lieu de rester muet', async () => {
    mockMutateCode.mockImplementation((_v: unknown, opts: any) =>
      opts.onError({
        response: { data: { message: 'La mission doit être démarrée avant de générer un code de fin.' } },
      }),
    );

    rendre();
    fireEvent.press(screen.getByLabelText('Afficher mon code de fin'));

    await waitFor(() =>
      expect(screen.getByText(/La mission doit être démarrée/)).toBeTruthy(),
    );
  });
});
