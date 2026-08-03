/**
 * L'écran de liste générique du moteur de console.
 *
 * Un seul écran rend tous les domaines : ce qu'il affiche vient entièrement du descripteur servi
 * avec la page. Ces tests l'éprouvent sur un descripteur d'essai, pour que les défauts du moteur
 * ne se mêlent pas à ceux d'un domaine réel.
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
  useNavigation: () => ({ navigate: mockNavigate, setOptions: jest.fn() }),
}));

import { apiClient } from '@/api';
import { ResourceListScreen } from '@/admin/console/ResourceListScreen';

const apiMock = new MockAdapter(apiClient);

const DESCRIPTEUR = {
  key: 'fake-users',
  columns: [
    { key: 'name', label: 'Nom', type: 'text' },
    { key: 'created_at', label: 'Inscrit le', type: 'date' },
    { key: 'is_active', label: 'Actif', type: 'bool' },
  ],
  filters: [
    { key: 'q', label: 'Rechercher', type: 'search', options: [] },
    {
      key: 'role',
      label: 'Rôle',
      type: 'select',
      options: [
        { value: 'client', label: 'Client' },
        { value: 'admin', label: 'Administrateur' },
      ],
    },
  ],
  sorts: ['id', 'name'],
  default_sort: 'id',
  actions: [{ key: 'ping', label: 'Ping', destructive: false, confirm: null }],
  form: [{ key: 'name', label: 'Nom', type: 'text', required: true, options: [] }],
};

const PAGE_1 = {
  ok: true,
  resource: DESCRIPTEUR,
  rows: [
    { id: 1, name: 'Amélie Durand', created_at: '2026-08-01T10:00:00Z', is_active: true },
    { id: 2, name: 'Bertrand Petit', created_at: '2026-08-02T10:00:00Z', is_active: false },
  ],
  next_cursor: 'curseur-2',
};

const PAGE_2 = {
  ok: true,
  resource: DESCRIPTEUR,
  rows: [{ id: 3, name: 'Claire Moreau', created_at: '2026-08-03T10:00:00Z', is_active: true }],
  next_cursor: null,
};

function renderScreen(params = { resource: 'fake-users', title: 'Utilisateurs' }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <ResourceListScreen route={{ params }} />
    </QueryClientProvider>,
  );
}

describe('ResourceListScreen', () => {
  beforeEach(() => {
    apiMock.reset();
    mockNavigate.mockClear();
  });

  it('rend les lignes selon les colonnes du descripteur', async () => {
    apiMock.onGet('/admin/console/fake-users').reply(200, PAGE_1);

    renderScreen();

    expect(await screen.findByText('Amélie Durand')).toBeTruthy();
    expect(screen.getByText('Bertrand Petit')).toBeTruthy();
  });

  it('formate chaque cellule selon son type déclaré', async () => {
    apiMock.onGet('/admin/console/fake-users').reply(200, PAGE_1);

    renderScreen();

    await screen.findByText('Amélie Durand');

    // Les colonnes secondaires sont jointes sur une ligne de méta — un tableau à colonnes ne tient
    // pas sur 390 px de large. On cherche donc la valeur DANS la ligne, pas comme nœud isolé.
    expect(screen.getByText(/01\/08\/2026/)).toBeTruthy();
    expect(screen.queryByText(/2026-08-01T10:00:00Z/)).toBeNull();
    // `is_active` est déclaré `bool` : rendu « Oui » / « Non », pas « true » / « false ».
    expect(screen.getByText(/· Non$/)).toBeTruthy();
    expect(screen.queryByText(/false/)).toBeNull();
  });

  it('charge la page suivante par curseur', async () => {
    apiMock
      .onGet('/admin/console/fake-users', { params: {} })
      .reply(200, PAGE_1)
      .onGet('/admin/console/fake-users', { params: { cursor: 'curseur-2' } })
      .reply(200, PAGE_2);

    renderScreen();

    await screen.findByText('Amélie Durand');
    fireEvent(screen.getByTestId('resource-list'), 'onEndReached');

    await waitFor(() => expect(screen.getByText('Claire Moreau')).toBeTruthy());
    // La première page reste : on ajoute, on ne remplace pas.
    expect(screen.getByText('Amélie Durand')).toBeTruthy();
  });

  it('n’essaie pas de charger au-delà de la dernière page', async () => {
    apiMock.onGet('/admin/console/fake-users').reply(200, { ...PAGE_1, next_cursor: null });

    renderScreen();

    await screen.findByText('Amélie Durand');
    const avant = apiMock.history.get.length;

    fireEvent(screen.getByTestId('resource-list'), 'onEndReached');

    await waitFor(() => expect(apiMock.history.get.length).toBe(avant));
  });

  it('transmet la recherche au serveur, sans filtrer localement', async () => {
    apiMock.onGet('/admin/console/fake-users').reply(200, PAGE_1);

    renderScreen();

    await screen.findByText('Amélie Durand');
    fireEvent.changeText(screen.getByLabelText('Rechercher'), 'Bertrand');

    // Filtrer localement ne verrait que la page chargée : la recherche doit porter sur tout le
    // domaine, donc partir au serveur.
    await waitFor(() => {
      const derniere = apiMock.history.get[apiMock.history.get.length - 1];
      expect(derniere?.params).toMatchObject({ 'filters[q]': 'Bertrand' });
    });
  });

  it('ouvre le détail d’une ligne', async () => {
    apiMock.onGet('/admin/console/fake-users').reply(200, PAGE_1);

    renderScreen();

    fireEvent.press(await screen.findByText('Amélie Durand'));

    expect(mockNavigate).toHaveBeenCalledWith(
      'AdminResourceDetail',
      expect.objectContaining({ resource: 'fake-users', id: 1 }),
    );
  });

  it('propose la création quand le descripteur porte un formulaire', async () => {
    apiMock.onGet('/admin/console/fake-users').reply(200, PAGE_1);

    renderScreen();

    fireEvent.press(await screen.findByLabelText('Créer'));

    expect(mockNavigate).toHaveBeenCalledWith(
      'AdminResourceForm',
      expect.objectContaining({ resource: 'fake-users' }),
    );
  });

  it('ne propose pas la création sur un domaine en lecture seule', async () => {
    apiMock
      .onGet('/admin/console/fake-users')
      .reply(200, { ...PAGE_1, resource: { ...DESCRIPTEUR, form: [] } });

    renderScreen();

    await screen.findByText('Amélie Durand');
    // Proposer un formulaire que le serveur refusera (405) ferait perdre la saisie.
    expect(screen.queryByLabelText('Créer')).toBeNull();
  });

  it('annonce une liste vide plutôt qu’un écran blanc', async () => {
    apiMock
      .onGet('/admin/console/fake-users')
      .reply(200, { ok: true, resource: DESCRIPTEUR, rows: [], next_cursor: null });

    renderScreen();

    expect(await screen.findByText('Aucun résultat')).toBeTruthy();
  });

  it('laisse réessayer quand le serveur refuse', async () => {
    apiMock.onGet('/admin/console/fake-users').replyOnce(500);

    renderScreen();

    const retry = await screen.findByLabelText('Réessayer');
    apiMock.onGet('/admin/console/fake-users').reply(200, PAGE_1);
    fireEvent.press(retry);

    await waitFor(() => expect(screen.getByText('Amélie Durand')).toBeTruthy());
  });
});
