/**
 * Un refus de suppression affiche SES RAISONS, à l'écran, dans la vraie chaîne.
 *
 * Le test unitaire de `readServerErrors` prouve que les raisons sont lues. Il ne prouve pas
 * qu'elles arrivent jusqu'à l'écran : entre les deux il y a l'intercepteur du client HTTP, qui
 * jetait justement le corps de la réponse pour n'en garder que le code.
 *
 * Ce fichier fait le chemin entier — 409 du serveur, intercepteur, hook, écran — parce que c'est
 * exactement là que l'information se perdait.
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

/*
 * `Alert.alert` ne rend rien dans l'environnement de test : on garde les boutons pour pouvoir
 * appuyer sur « Supprimer » comme un utilisateur le ferait.
 */
const boutonsDAlerte: { text: string; onPress?: () => void }[] = [];

jest.spyOn(require('react-native').Alert, 'alert').mockImplementation(
  (_titre: unknown, _corps: unknown, boutons?: unknown) => {
    boutonsDAlerte.length = 0;

    for (const b of (boutons ?? []) as { text: string; onPress?: () => void }[]) {
      boutonsDAlerte.push(b);
    }
  },
);

import { apiClient } from '@/api';
import { ResourceDetailScreen } from '@/admin/console/ResourceDetailScreen';

const apiMock = new MockAdapter(apiClient);

const DESCRIPTEUR = {
  key: 'countries',
  columns: [{ key: 'name', label: 'Nom', type: 'text' }],
  filters: [],
  sorts: ['id'],
  default_sort: 'id',
  actions: [],
  form: [{ key: 'name', label: 'Nom', type: 'text', required: true, options: [] }],
  global_actions: [],
};

beforeEach(() => {
  apiMock.reset();
  boutonsDAlerte.length = 0;

  apiMock
    // Sans paramètre, l'URL n'a pas de « ? » : l'exiger empêchait le descripteur d'arriver, et
    // le bouton dépend de lui.
    .onGet(/\/admin\/console\/countries$/)
    .reply(200, { ok: true, resource: DESCRIPTEUR, rows: [], next_cursor: null });

  apiMock
    .onGet('/admin/console/countries/7')
    .reply(200, { ok: true, row: { id: 7, name: 'Belgique' } });
});

function afficher() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <ResourceDetailScreen route={{ params: { resource: 'countries', title: 'Pays', id: 7 } }} />
    </QueryClientProvider>,
  );
}

it('les raisons du serveur sont affichées telles quelles', async () => {
  apiMock.onDelete('/admin/console/countries/7').reply(409, {
    ok: false,
    error_code: 'delete_refused',
    reasons: ['3 zones rattachées', '12 missions en cours'],
  });

  afficher();

  fireEvent.press(await screen.findByText('Supprimer'));

  // L'alerte de confirmation ne s'affiche pas en test : on appuie sur son bouton destructif.
  const confirmer = boutonsDAlerte.find((b) => b.text !== 'Annuler');
  confirmer?.onPress?.();

  await waitFor(() => {
    /*
     * C'est la seule information qui permette d'agir. Sans elle, l'administrateur apprend qu'il ne
     * peut pas supprimer, jamais quoi détacher d'abord — et retourne au poste de travail pour
     * l'apprendre.
     */
    expect(screen.getByText(/3 zones rattachées/)).toBeTruthy();
    expect(screen.getByText(/12 missions en cours/)).toBeTruthy();
  });
});

it('un refus sans raison garde un message simple', async () => {
  apiMock
    .onDelete('/admin/console/countries/7')
    .reply(403, { ok: false, error_code: 'forbidden_readonly' });

  afficher();

  fireEvent.press(await screen.findByText('Supprimer'));
  boutonsDAlerte.find((b) => b.text !== 'Annuler')?.onPress?.();

  await waitFor(() => {
    // Ni puce orpheline ni ligne blanche : le message reste celui du code seul.
    expect(screen.getByText(/lecture seule/)).toBeTruthy();
  });
});
