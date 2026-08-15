import React from 'react';
import { render } from '@testing-library/react-native';
import { BookingDetailScreen } from '../BookingDetailScreen';
import * as booking from '@/booking';

jest.mock('@/booking', () => ({
  __esModule: true,
  useBookingDetail: jest.fn(),
}));

jest.mock('@react-navigation/native', () => ({
  __esModule: true,
  useNavigation: () => ({ navigate: jest.fn() }),
}));

/*
 * L'écran demande désormais son code de fin, donc il appelle `useMutation`.
 *
 * Ce fichier rend le composant SANS `QueryClientProvider` — c'est son parti pris, il bouchonne les
 * accès au serveur plutôt que de monter un client. Le bouchon est ajouté ici pour la même raison
 * que celui de `@/booking` : ces tests portent sur le rendu, pas sur les échanges réseau.
 */
jest.mock('@/tracking', () => ({
  __esModule: true,
  useCompletionCode: () => ({ mutate: jest.fn(), isPending: false }),
}));

const route: any = { params: { bookingId: 42 } };
const navigation: any = { navigate: jest.fn() };

const baseBooking = {
  id: 42,
  service_name: 'Nettoyage complet',
  status: 'confirmed',
  scheduled_date: '2026-06-10',
  scheduled_time: '09:00',
  address: '12 rue de la Paix',
  city: 'Paris',
  provider_name: 'Alice',
  total_price: 120,
  contract_covered: true,
};

const mockDetail = (overrides: Record<string, unknown>) =>
  (booking.useBookingDetail as jest.Mock).mockReturnValue({
    data: undefined,
    isLoading: false,
    isError: false,
    refetch: jest.fn(),
    ...overrides,
  });

describe('BookingDetailScreen polish', () => {
  beforeEach(() => jest.clearAllMocks());

  it('renders the contract-coverage badge and DetailRow label/value when contract_covered', () => {
    mockDetail({ data: baseBooking });
    const { getByTestId, getByText } = render(
      <BookingDetailScreen route={route} navigation={navigation} />,
    );
    expect(getByTestId('contract-coverage-badge')).toBeTruthy();
    // a DetailRow renders its label and value
    expect(getByText('Date')).toBeTruthy();
    // La date est écrite en français, plus recopiée depuis l'API : « 2026-06-10 à 09:00 »
    // s'affichait tel quel au client, au milieu d'une app par ailleurs francophone.
    expect(getByText('10 juin 2026 à 09h00')).toBeTruthy();
  });

  it('shows the error state when the query errors', () => {
    mockDetail({ isError: true });
    const { getByText } = render(
      <BookingDetailScreen route={route} navigation={navigation} />,
    );
    expect(getByText(/erreur|Oups/i)).toBeTruthy();
  });

  it('shows an empty state when the booking is absent (not loading, no error)', () => {
    mockDetail({ data: undefined });
    const { getByText } = render(
      <BookingDetailScreen route={route} navigation={navigation} />,
    );
    expect(getByText(/introuvable|aucun|empty/i)).toBeTruthy();
  });
});
