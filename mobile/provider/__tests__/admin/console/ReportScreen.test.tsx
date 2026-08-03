/**
 * L'écran de synthèse — les modules qui ne sont pas des listes.
 *
 * Dix pages d'administration n'ont aucune table derrière elles. Les rendre en liste aurait montré
 * un écran vide en prétendant couvrir le domaine ; elles sont servies en tuiles chiffrées.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

notifyManager.setScheduler((callback) => callback());

jest.mock('@/storage/secureStore');

import { apiClient } from '@/api';
import { ReportScreen } from '@/admin/console/ReportScreen';

const apiMock = new MockAdapter(apiClient);

const SECTIONS = [
  {
    title: 'À traiter',
    tiles: [
      { key: 'bookings_pending', label: 'Réservations en attente', value: 7, format: 'number', hint: null, tone: 'warning', available: true },
      { key: 'claims_open', label: 'Litiges ouverts', value: 0, format: 'number', hint: null, tone: 'neutral', available: false },
    ],
  },
  {
    title: 'Valeur',
    tiles: [
      { key: 'revenue', label: 'Chiffre d’affaires', value: 1234.5, format: 'money', hint: null, tone: 'neutral', available: true },
    ],
  },
];

function renderReport() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <ReportScreen route={{ params: { report: 'home', title: 'Accueil admin' } }} />
    </QueryClientProvider>,
  );
}

describe('ReportScreen', () => {
  beforeEach(() => apiMock.reset());

  it('rend les sections et leurs tuiles', async () => {
    apiMock.onGet('/admin/console/reports/home').reply(200, { ok: true, sections: SECTIONS });

    renderReport();

    expect(await screen.findByText('À traiter')).toBeTruthy();
    expect(screen.getByText('Réservations en attente')).toBeTruthy();
    expect(screen.getByText('7')).toBeTruthy();
    expect(screen.getByText('Valeur')).toBeTruthy();
  });

  it('ne présente pas une tuile non mesurable comme un zéro', async () => {
    apiMock.onGet('/admin/console/reports/home').reply(200, { ok: true, sections: SECTIONS });

    renderReport();

    await screen.findByText('Réservations en attente');

    // Un « 0 litige ouvert » affiché parce que la requête a échoué ferait croire à un calme qui
    // n'existe pas, et personne n'irait vérifier.
    expect(screen.getByText('—')).toBeTruthy();
    expect(screen.getByText('non mesurable')).toBeTruthy();
  });

  it('formate un montant selon son format déclaré', async () => {
    apiMock.onGet('/admin/console/reports/home').reply(200, { ok: true, sections: SECTIONS });

    renderReport();

    await screen.findByText('Chiffre d’affaires');
    expect(screen.getByText(/€/)).toBeTruthy();
  });

  it('laisse réessayer quand le serveur refuse', async () => {
    apiMock.onGet('/admin/console/reports/home').replyOnce(500);

    renderReport();

    const retry = await screen.findByLabelText('Réessayer');
    apiMock.onGet('/admin/console/reports/home').reply(200, { ok: true, sections: SECTIONS });
    fireEvent.press(retry);

    await waitFor(() => expect(screen.getByText('Réservations en attente')).toBeTruthy());
  });

  it('montre un squelette pendant le chargement', () => {
    apiMock.onGet('/admin/console/reports/home').reply(() => new Promise(() => {}));

    renderReport();

    expect(screen.getByTestId('report-loading')).toBeTruthy();
  });
});
