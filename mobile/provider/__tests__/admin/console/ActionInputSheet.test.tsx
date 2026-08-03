/**
 * Le parcours d'une action qui exige des valeurs — le refus motivé.
 *
 * Quatre files de la plateforme (litige, KYC, KYB, approbation) demandent un motif écrit. Le
 * moteur les sert toutes avec la même feuille : l'action DÉCLARE ce qu'elle exige, la feuille le
 * demande, le serveur le valide. Ces tests décrivent ce parcours de bout en bout.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

notifyManager.setScheduler((callback) => callback());

jest.mock('@/storage/secureStore');

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn(), goBack: jest.fn() }),
}));

import { apiClient } from '@/api';
import { ResourceDetailScreen } from '@/admin/console/ResourceDetailScreen';

const apiMock = new MockAdapter(apiClient);

const REFUS = {
  key: 'reject',
  label: 'Refuser',
  destructive: true,
  confirm: 'La vérification sera refusée et la personne en sera informée.',
  fields: [
    { key: 'reason', label: 'Motif du refus', type: 'textarea', required: true, options: [] },
  ],
};

const VALIDER = {
  key: 'approve',
  label: 'Valider',
  destructive: false,
  confirm: null,
  fields: [],
};

const DESCRIPTEUR = {
  key: 'kyc',
  columns: [{ key: 'status', label: 'Statut', type: 'badge' }],
  filters: [],
  sorts: ['id'],
  default_sort: 'id',
  actions: [VALIDER, REFUS],
  form: [],
};

function renderDetail() {
  apiMock
    .onGet('/admin/console/kyc')
    .reply(200, { ok: true, resource: DESCRIPTEUR, rows: [], next_cursor: null });
  apiMock
    .onGet('/admin/console/kyc/7')
    .reply(200, { ok: true, row: { id: 7, status: 'in_review' } });

  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <ResourceDetailScreen route={{ params: { resource: 'kyc', title: 'KYC', id: 7 } }} />
    </QueryClientProvider>,
  );
}

describe('action exigeant une saisie', () => {
  beforeEach(() => apiMock.reset());

  it('ouvre une feuille de saisie au lieu d’agir directement', async () => {
    renderDetail();

    fireEvent.press(await screen.findByText('Refuser'));

    expect(await screen.findByTestId('action-input-sheet')).toBeTruthy();
    expect(screen.getByLabelText('Motif du refus')).toBeTruthy();
    // Rien n'est parti tant que le motif n'est pas écrit.
    expect(apiMock.history.post).toHaveLength(0);
  });

  it('affiche ce que l’action va faire au-dessus de la saisie', async () => {
    renderDetail();

    fireEvent.press(await screen.findByText('Refuser'));

    // Le texte est lu pendant qu'on écrit le motif, plutôt que validé dans une alerte puis oublié.
    expect(
      await screen.findByText('La vérification sera refusée et la personne en sera informée.'),
    ).toBeTruthy();
  });

  it('envoie le motif saisi au serveur', async () => {
    apiMock.onPost('/admin/console/kyc/7/actions/reject').reply(200, { ok: true, result: {} });

    renderDetail();

    fireEvent.press(await screen.findByText('Refuser'));
    fireEvent.changeText(
      await screen.findByLabelText('Motif du refus'),
      'Document illisible : la date de naissance n’apparaît pas.',
    );
    // Le libellé « Refuser » apparaît aussi sur le bouton du détail : on cible le bouton d'envoi
    // par son testID plutôt que de parier sur un ordre de rendu.
    fireEvent.press(within(screen.getByTestId('action-input-submit')).getByText('Refuser'));

    await waitFor(() => {
      const envoi = apiMock.history.post[0];
      expect(JSON.parse(envoi?.data ?? '{}')).toEqual({
        reason: 'Document illisible : la date de naissance n’apparaît pas.',
      });
    });
  });

  it('garde la feuille ouverte et pose l’erreur du serveur sur le champ', async () => {
    apiMock.onPost('/admin/console/kyc/7/actions/reject').reply(422, {
      ok: false,
      error: 'validation_failed',
      error_code: 'validation_failed',
      errors: { reason: ['Le motif doit contenir au moins 10 caractères.'] },
    });

    renderDetail();

    fireEvent.press(await screen.findByText('Refuser'));
    fireEvent.changeText(await screen.findByLabelText('Motif du refus'), 'court');
    fireEvent.press(within(screen.getByTestId('action-input-submit')).getByText('Refuser'));

    expect(await screen.findByText('Le motif doit contenir au moins 10 caractères.')).toBeTruthy();
    // La refermer effacerait ce que l'utilisateur vient d'écrire.
    expect(screen.getByTestId('action-input-sheet')).toBeTruthy();
  });

  it('n’ouvre aucune feuille pour une action sans saisie', async () => {
    apiMock.onPost('/admin/console/kyc/7/actions/approve').reply(200, { ok: true, result: {} });

    renderDetail();

    fireEvent.press(await screen.findByText('Valider'));

    await waitFor(() => expect(apiMock.history.post).toHaveLength(1));
    expect(screen.queryByTestId('action-input-sheet')).toBeNull();
  });
});
