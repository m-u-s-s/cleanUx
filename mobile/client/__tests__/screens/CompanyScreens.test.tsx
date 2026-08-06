import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import { CompanySitesScreen } from '@/screens/company/CompanySitesScreen';
import { CompanySigningAppointmentsScreen } from '@/screens/company/CompanySigningAppointmentsScreen';
import { ProfileScreen } from '@/screens/ProfileScreen';
import { apiClient } from '@/api';

/**
 * L'ESPACE SOCIÉTÉ CLIENTE SUR MOBILE.
 *
 * Il n'existait que sur le web : `routes/api/client.php` n'exposait que l'annuaire des sociétés
 * PRESTATAIRES à parcourir et les réservations — rien de la société de l'appelant. L'API
 * `/client/company/*` a été créée avec ces écrans.
 *
 * LE TEST DE LA PORTE EST LE PLUS IMPORTANT ICI. Côté prestataire, tous les écrans étaient testés
 * et pourtant invisibles : la condition d'affichage exigeait `is_entreprise === true`, un drapeau
 * qui signifie « société CLIENTE » côté serveur — donc mutuellement exclusif avec
 * `provider_company`. Personne ne testait la porte, seulement les pièces derrière.
 */

jest.mock('@/api', () => ({
  apiClient: { get: jest.fn(), post: jest.fn() },
}));

const mockUser: { value: Record<string, unknown> | null } = { value: null };

jest.mock('@/auth', () => ({
  useAuth: () => ({ user: mockUser.value, logout: jest.fn() }),
}));

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
}));

const mockGet = apiClient.get as jest.Mock;
const mockPost = apiClient.post as jest.Mock;

function afficher(composant: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(<QueryClientProvider client={client}>{composant}</QueryClientProvider>);
}

beforeEach(() => {
  jest.clearAllMocks();
  mockGet.mockResolvedValue({ data: { data: [] } });
});

describe('Porte de l’espace société cliente', () => {
  it("s'ouvre pour un membre d'une société CLIENTE", () => {
    mockUser.value = { name: 'Acheteuse', organization_type: 'client_company', is_entreprise: true };

    const { getByText } = afficher(<ProfileScreen />);

    getByText('Mes locaux');
    getByText('Demande multi-locaux');
    getByText('Signatures sur place');
  });

  it('reste fermée pour un particulier', () => {
    mockUser.value = { name: 'Particulier', organization_type: null, is_entreprise: false };

    const { queryByText } = afficher(<ProfileScreen />);

    expect(queryByText('Mes locaux')).toBeNull();
    expect(queryByText('Demande multi-locaux')).toBeNull();
  });

  it("reste fermée pour un membre d'une société PRESTATAIRE", () => {
    // Cette application est celle des clients : lui proposer l'espace prestataire donnerait des
    // liens qui répondent 403 à qui les ouvre.
    mockUser.value = { name: 'Prestataire', organization_type: 'provider_company', is_entreprise: false };

    const { queryByText } = afficher(<ProfileScreen />);

    expect(queryByText('Mes locaux')).toBeNull();
  });
});

describe('CompanySitesScreen', () => {
  it('liste les locaux renvoyés par /client/company/sites', async () => {
    mockGet.mockResolvedValue({
      data: { data: [{ id: 3, name: 'Siège Lyon', code: 'LYON-01', city: 'Lyon', address: null }] },
    });

    const { getByText } = afficher(<CompanySitesScreen />);

    await waitFor(() => getByText('Siège Lyon'));
    expect(mockGet).toHaveBeenCalledWith('/client/company/sites');
  });
});

describe('CompanySigningAppointmentsScreen', () => {
  it('planifie un rendez-vous à distance quand aucun local n’est choisi', async () => {
    mockPost.mockResolvedValue({ data: {} });

    const { getByText, getByTestId } = afficher(<CompanySigningAppointmentsScreen />);

    await waitFor(() => getByText('À distance'));

    fireEvent.changeText(getByTestId('champ-date'), '2026-09-01 10:00');
    fireEvent.press(getByText('Planifier'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/client/company/signing-appointments', {
        scheduled_at: '2026-09-01 10:00',
        organization_site_id: null,
        notes: null,
      }),
    );
  });
});
