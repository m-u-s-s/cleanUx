/**
 * Les actions GLOBALES partent vraiment, et les refus disent vraiment pourquoi.
 *
 * Les gardes de `gestesGlobauxEtRefus.test.ts` vérifient que le câblage EXISTE — elles lisent des
 * fichiers. Elles ne disent rien de l'URL réellement appelée ni du message réellement affiché.
 * Ce fichier-là fait partir la requête et lit ce qui revient à l'écran.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

notifyManager.setScheduler((callback) => callback());

jest.mock('@/storage/secureStore');

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn(), goBack: jest.fn() }),
}));

import { apiClient } from '@/api';
import { ResourceListScreen } from '@/admin/console/ResourceListScreen';
import { readServerErrors } from '@/admin/console/hooks';

const apiMock = new MockAdapter(apiClient);

const DESCRIPTEUR = {
  key: 'matching',
  columns: [{ key: 'name', label: 'Nom', type: 'text' }],
  filters: [],
  sorts: ['id'],
  default_sort: 'id',
  actions: [],
  form: [],
  global_actions: [
    { key: 'purge', label: 'Purger le cache', fields: [], confirm: null },
    {
      key: 'simulate',
      label: 'Simuler le matching',
      confirm: null,
      fields: [
        {
          key: 'booking_id',
          label: 'Identifiant de la mission',
          type: 'number',
          required: true,
          options: [],
        },
      ],
    },
  ],
};

const PAGE = { ok: true, resource: DESCRIPTEUR, rows: [], next_cursor: null };

function afficher() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <ResourceListScreen route={{ params: { resource: 'matching', title: 'Matching' } }} />
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  apiMock.reset();
  apiMock.onGet(/\/admin\/console\/matching(\?|$)/).reply(200, PAGE);
});

describe('actions globales — le geste part', () => {
  it('une action sans paramètre appelle la route SANS identifiant de ligne', async () => {
    apiMock.onPost('/admin/console/matching/actions/purge').reply(200, { ok: true, result: [] });

    afficher();

    fireEvent.press(await screen.findByText('Purger le cache'));

    await waitFor(() => {
      const envoi = apiMock.history.post.find((r) => r.url?.includes('/actions/purge'));

      /*
       * `/matching/actions/purge` et NON `/matching/{id}/actions/purge`. Réutiliser le hook par
       * ligne aurait demandé d'inventer un identifiant, et le geste aurait visé une ligne au
       * hasard — ici, la liste est même vide.
       */
      expect(envoi?.url).toBe('/admin/console/matching/actions/purge');
    });
  });

  it('une action à paramètre ouvre la saisie et envoie ce qui a été saisi', async () => {
    apiMock.onPost('/admin/console/matching/actions/simulate').reply(200, { ok: true, result: [] });

    afficher();

    fireEvent.press(await screen.findByText('Simuler le matching'));

    // La feuille demande le paramètre que l'action a DÉCLARÉ exiger.
    const champ = await screen.findByLabelText('Identifiant de la mission');
    fireEvent.changeText(champ, '4242');

    /*
     * La feuille reprend le LIBELLÉ de l'action pour son bouton d'envoi : deux éléments portent
     * donc « Simuler le matching » — celui de la liste, qui ouvre, et celui de la feuille, qui
     * envoie. Le second est rendu après ; viser le premier rouvrirait la feuille sans rien envoyer.
     */
    const boutons = screen.getAllByText('Simuler le matching');
    const envoi = boutons.at(-1);

    if (! envoi) {
      throw new Error('La feuille de saisie ne rend pas son bouton d’envoi.');
    }

    fireEvent.press(envoi);

    await waitFor(() => {
      const envoi = apiMock.history.post.find((r) => r.url?.includes('/actions/simulate'));

      expect(JSON.parse(envoi?.data ?? '{}')).toEqual({ booking_id: '4242' });
    });
  });

  it('un module sans action globale n’affiche aucun bouton', async () => {
    apiMock.reset();
    apiMock
      .onGet(/\/admin\/console\/matching(\?|$)/)
      .reply(200, { ...PAGE, resource: { ...DESCRIPTEUR, global_actions: [] } });

    afficher();

    await screen.findByTestId('resource-list');

    expect(screen.queryByText('Purger le cache')).toBeNull();
  });
});

describe('refus motivés — la raison arrive à l’écran', () => {
  it('un refus de suppression rend les raisons du serveur', () => {
    /*
     * Le serveur répond 409 avec la liste. C'est la seule information qui permette d'agir : sans
     * elle, l'administrateur apprend qu'il ne peut pas supprimer, mais pas quoi détacher d'abord.
     */
    const refus = {
      errorCode: 'delete_refused',
      payload: { reasons: ['3 zones rattachées', '12 missions en cours'] },
    };

    const { message } = readServerErrors(refus);

    expect(message).toContain('3 zones rattachées');
    expect(message).toContain('12 missions en cours');
  });

  it('un refus lecture seule est dit en clair', () => {
    const { message } = readServerErrors({ errorCode: 'forbidden_readonly' });

    expect(message).toContain('lecture seule');
    // « Une erreur est survenue » aurait laissé croire à une panne plutôt qu'à une permission.
    expect(message).not.toContain('Une erreur est survenue');
  });

  it('un refus sans raison garde son message simple', () => {
    const { message } = readServerErrors({ errorCode: 'not_found' });

    // Pas de puce vide, pas de ligne blanche en fin de message.
    expect(message).toBe('Cet élément n’existe plus.');
  });
});
