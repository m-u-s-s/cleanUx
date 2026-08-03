/**
 * L'annuaire des modules d'administration.
 *
 * Il affiche TOUT le registre, y compris ce qui n'est pas encore servi nativement, marqué et non
 * navigable. Masquer les modules non couverts donnerait une application qui a l'air complète et
 * un avancement que personne ne peut mesurer.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

notifyManager.setScheduler((callback) => callback());

jest.mock('@/storage/secureStore');

const mockNavigate = jest.fn();
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

import { apiClient } from '@/api';
import { AdminDirectoryScreen } from '@/admin/AdminDirectoryScreen';

const apiMock = new MockAdapter(apiClient);

const CATALOG = {
  ok: true,
  groups: [
    {
      key: 'personnes',
      title: 'Personnes et comptes',
      modules: [
        { key: 'users', title: 'Utilisateurs', icon: 'people-outline', coverage: 'screen', route: 'admin/utilisateurs' },
        { key: 'kyc', title: 'Vérifications KYC', icon: 'finger-print-outline', coverage: 'pending', route: 'admin/kyc' },
      ],
    },
    {
      key: 'argent',
      title: 'Argent et conformité',
      modules: [
        { key: 'accounting', title: 'Comptabilité', icon: 'calculator-outline', coverage: 'descriptor', route: 'admin/accounting-v2' },
      ],
    },
  ],
  counts: { total: 3, covered: 2, pending: 1 },
};

function renderScreen() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <AdminDirectoryScreen />
    </QueryClientProvider>,
  );
}

describe('AdminDirectoryScreen', () => {
  beforeEach(() => {
    apiMock.reset();
    mockNavigate.mockClear();
  });

  it('rend les groupes et leurs modules', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    expect(await screen.findByText('Personnes et comptes')).toBeTruthy();
    expect(screen.getByText('Utilisateurs')).toBeTruthy();
    expect(screen.getByText('Vérifications KYC')).toBeTruthy();
    expect(screen.getByText('Argent et conformité')).toBeTruthy();
    expect(screen.getByText('Comptabilité')).toBeTruthy();
  });

  it('affiche l’avancement réel de la couverture', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    expect(await screen.findByText('2 / 3 modules disponibles')).toBeTruthy();
  });

  it('marque « à venir » ce qui n’est pas encore servi', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    expect(await screen.findByText('À venir')).toBeTruthy();
  });

  it('ouvre un module couvert dans le moteur de console', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    fireEvent.press(await screen.findByText('Utilisateurs'));

    // La clé de module EST la clé de ressource : le registre serveur refuse qu'elles divergent.
    expect(mockNavigate).toHaveBeenCalledWith('AdminResourceList', {
      resource: 'users',
      title: 'Utilisateurs',
    });
  });

  it('ne navigue pas vers un module non couvert', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    fireEvent.press(await screen.findByText('Vérifications KYC'));

    // Ouvrir un écran vide serait pire que l'annoncer : la marque « à venir » doit tenir.
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  it('filtre par titre', async () => {
    apiMock.onGet('/admin/catalog').reply(200, CATALOG);

    renderScreen();

    await screen.findByText('Utilisateurs');
    fireEvent.changeText(screen.getByLabelText('Rechercher un module'), 'compta');

    await waitFor(() => expect(screen.queryByText('Utilisateurs')).toBeNull());
    expect(screen.getByText('Comptabilité')).toBeTruthy();
  });

  it('laisse réessayer quand le serveur refuse', async () => {
    apiMock.onGet('/admin/catalog').replyOnce(500);

    renderScreen();

    const retry = await screen.findByLabelText('Réessayer');

    apiMock.onGet('/admin/catalog').reply(200, CATALOG);
    fireEvent.press(retry);

    await waitFor(() => expect(screen.getByText('Utilisateurs')).toBeTruthy());
  });
});
