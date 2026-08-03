/**
 * Le formulaire générique du moteur.
 *
 * Il n'existe qu'une fois pour tous les domaines : ce qu'il affiche vient du descripteur, et la
 * validation reste au serveur — le mobile ne connaît que le type et le caractère obligatoire.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

notifyManager.setScheduler((callback) => callback());

jest.mock('@/storage/secureStore');

const mockGoBack = jest.fn();
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ goBack: mockGoBack, navigate: jest.fn() }),
}));

import { apiClient } from '@/api';
import { ResourceFormScreen } from '@/admin/console/ResourceFormScreen';

const apiMock = new MockAdapter(apiClient);

const DESCRIPTEUR = {
  key: 'fake-users',
  columns: [{ key: 'name', label: 'Nom', type: 'text' }],
  filters: [],
  sorts: ['id'],
  default_sort: 'id',
  actions: [],
  form: [
    { key: 'name', label: 'Nom', type: 'text', required: true, options: [] },
    { key: 'email', label: 'Email', type: 'email', required: true, options: [] },
    { key: 'is_active', label: 'Actif', type: 'bool', required: false, options: [] },
  ],
};

const PAGE = { ok: true, resource: DESCRIPTEUR, rows: [], next_cursor: null };

function renderForm(params: { resource: string; title: string; id?: number }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <ResourceFormScreen route={{ params }} />
    </QueryClientProvider>,
  );
}

describe('ResourceFormScreen', () => {
  beforeEach(() => {
    apiMock.reset();
    mockGoBack.mockClear();
    apiMock.onGet('/admin/console/fake-users').reply(200, PAGE);
  });

  it('rend un champ par entrée du formulaire déclaré', async () => {
    renderForm({ resource: 'fake-users', title: 'Utilisateurs' });

    expect(await screen.findByLabelText('Nom')).toBeTruthy();
    expect(screen.getByLabelText('Email')).toBeTruthy();
    expect(screen.getByLabelText('Actif')).toBeTruthy();
  });

  it('marque les champs obligatoires', async () => {
    renderForm({ resource: 'fake-users', title: 'Utilisateurs' });

    await screen.findByLabelText('Nom');
    expect(screen.getByText('Nom *')).toBeTruthy();
    // `is_active` n'est pas obligatoire : pas d'astérisque.
    expect(screen.queryByText('Actif *')).toBeNull();
  });

  it('n’envoie que les champs déclarés', async () => {
    apiMock.onPost('/admin/console/fake-users').reply(201, { ok: true, row: { id: 9 } });

    renderForm({ resource: 'fake-users', title: 'Utilisateurs' });

    fireEvent.changeText(await screen.findByLabelText('Nom'), 'Nouvelle Personne');
    fireEvent.press(screen.getByText('Créer'));

    await waitFor(() => {
      const envoi = apiMock.history.post[0];
      expect(JSON.parse(envoi?.data ?? '{}')).toEqual({ name: 'Nouvelle Personne' });
    });
  });

  it('pose les erreurs du serveur champ par champ', async () => {
    apiMock.onPost('/admin/console/fake-users').reply(422, {
      ok: false,
      // Le serveur sert les DEUX clés : `error` (convention historique) et `error_code` (ce que
      // l'intercepteur du client mobile lit). Le mock doit refléter la vraie réponse.
      error: 'validation_failed',
      error_code: 'validation_failed',
      errors: { email: ['Cette adresse est déjà utilisée.'] },
    });

    renderForm({ resource: 'fake-users', title: 'Utilisateurs' });

    fireEvent.changeText(await screen.findByLabelText('Nom'), 'Zoé');
    fireEvent.press(screen.getByText('Créer'));

    // Fondre les erreurs dans un message unique obligerait à relire tout le formulaire pour
    // trouver la ligne fautive.
    expect(await screen.findByText('Cette adresse est déjà utilisée.')).toBeTruthy();
  });

  it('affiche un message général quand le refus ne vise aucun champ', async () => {
    apiMock.onPost('/admin/console/fake-users').reply(405, {
      ok: false,
      error: 'read_only_resource',
      error_code: 'read_only_resource',
    });

    renderForm({ resource: 'fake-users', title: 'Utilisateurs' });

    fireEvent.changeText(await screen.findByLabelText('Nom'), 'Zoé');
    fireEvent.press(screen.getByText('Créer'));

    expect(await screen.findByText('Ce module est en lecture seule.')).toBeTruthy();
  });

  it('revient en arrière après un enregistrement réussi', async () => {
    apiMock.onPost('/admin/console/fake-users').reply(201, { ok: true, row: { id: 9 } });

    renderForm({ resource: 'fake-users', title: 'Utilisateurs' });

    fireEvent.changeText(await screen.findByLabelText('Nom'), 'Zoé');
    fireEvent.press(screen.getByText('Créer'));

    await waitFor(() => expect(mockGoBack).toHaveBeenCalled());
  });

  it('part des valeurs existantes en édition', async () => {
    apiMock
      .onGet('/admin/console/fake-users/7')
      .reply(200, { ok: true, row: { id: 7, name: 'Zoé Admin', email: 'zoe@example.test' } });
    apiMock.onPatch('/admin/console/fake-users/7').reply(200, { ok: true, row: { id: 7 } });

    renderForm({ resource: 'fake-users', title: 'Utilisateurs', id: 7 });

    await waitFor(() => expect(screen.getByLabelText('Nom').props.value).toBe('Zoé Admin'));
    expect(screen.getByText('Enregistrer')).toBeTruthy();
  });

  it('envoie une mise à jour partielle en édition', async () => {
    apiMock
      .onGet('/admin/console/fake-users/7')
      .reply(200, { ok: true, row: { id: 7, name: 'Zoé Admin', email: 'zoe@example.test' } });
    apiMock.onPatch('/admin/console/fake-users/7').reply(200, { ok: true, row: { id: 7 } });

    renderForm({ resource: 'fake-users', title: 'Utilisateurs', id: 7 });

    await waitFor(() => expect(screen.getByLabelText('Nom').props.value).toBe('Zoé Admin'));
    fireEvent.changeText(screen.getByLabelText('Nom'), 'Zoé Renommée');
    fireEvent.press(screen.getByText('Enregistrer'));

    await waitFor(() => {
      const envoi = apiMock.history.patch[0];
      const charge = JSON.parse(envoi?.data ?? '{}');
      // Les champs hors formulaire (id) ne partent jamais : le serveur les refuserait, et les
      // envoyer donnerait l'illusion qu'ils sont modifiables.
      expect(charge).not.toHaveProperty('id');
      expect(charge.name).toBe('Zoé Renommée');
    });
  });
});
