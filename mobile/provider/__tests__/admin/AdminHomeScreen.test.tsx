/**
 * L'accueil de la console d'administration.
 *
 * Sept compteurs exacts, un état d'erreur qui laisse réessayer, et un compteur non mesurable
 * distingué d'un zéro mesuré — un « 0 litige ouvert » affiché parce que la requête a échoué
 * ferait croire à un calme qui n'existe pas.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

notifyManager.setScheduler((callback) => callback());

jest.mock('@/storage/secureStore');

import { apiClient } from '@/api';
import { AdminHomeScreen } from '@/admin/AdminHomeScreen';

const apiMock = new MockAdapter(apiClient);

const KPIS = [
  { key: 'users', label: 'Comptes', icon: 'people-outline', value: 42, available: true },
  { key: 'bookings_pending', label: 'Réservations en attente', icon: 'hourglass-outline', value: 7, available: true },
  { key: 'bookings_today', label: 'Réservations du jour', icon: 'today-outline', value: 3, available: true },
  { key: 'missions_active', label: 'Missions en cours', icon: 'briefcase-outline', value: 2, available: true },
  { key: 'claims_open', label: 'Litiges ouverts', icon: 'alert-circle-outline', value: 0, available: false },
  { key: 'kyc_pending', label: 'KYC à traiter', icon: 'finger-print-outline', value: 5, available: true },
  { key: 'providers_pending', label: 'Prestataires à valider', icon: 'person-add-outline', value: 1, available: true },
];

function renderScreen() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <AdminHomeScreen />
    </QueryClientProvider>,
  );
}

describe('AdminHomeScreen', () => {
  beforeEach(() => apiMock.reset());

  it('affiche les sept compteurs servis', async () => {
    apiMock.onGet('/admin/overview').reply(200, { ok: true, kpis: KPIS });

    renderScreen();

    expect(await screen.findByText('Comptes')).toBeTruthy();
    expect(screen.getByText('42')).toBeTruthy();
    expect(screen.getByText('Réservations en attente')).toBeTruthy();
    expect(screen.getByText('7')).toBeTruthy();
    expect(screen.getByText('Prestataires à valider')).toBeTruthy();
  });

  it('ne présente pas un compteur non mesurable comme un zéro', async () => {
    apiMock.onGet('/admin/overview').reply(200, { ok: true, kpis: KPIS });

    renderScreen();

    // `claims_open` est servi avec available:false. Afficher « 0 » ferait croire à un calme
    // inexistant : l'écran doit dire qu'il ne sait pas.
    const litiges = await screen.findByLabelText(/Litiges ouverts/);
    expect(litiges).toBeTruthy();
    expect(screen.getByText('—')).toBeTruthy();
  });

  it('laisse réessayer quand le serveur refuse', async () => {
    apiMock.onGet('/admin/overview').replyOnce(500);

    renderScreen();

    const retry = await screen.findByLabelText('Réessayer');

    apiMock.onGet('/admin/overview').reply(200, { ok: true, kpis: KPIS });
    fireEvent.press(retry);

    await waitFor(() => expect(screen.getByText('Comptes')).toBeTruthy());
  });

  it('montre un squelette pendant le chargement', () => {
    apiMock.onGet('/admin/overview').reply(() => new Promise(() => {}));

    renderScreen();

    expect(screen.getByTestId('admin-home-loading')).toBeTruthy();
  });
});
