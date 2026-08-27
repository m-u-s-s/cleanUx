/**
 * Tests for DisputesScreen — opening a dispute (was read-only: list only).
 */
import React from 'react';
import { Alert } from 'react-native';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true, isInternetReachable: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true, isInternetReachable: true }),
  },
}));

import * as ImagePicker from 'expo-image-picker';
import { apiClient } from '@/api';
import { DisputesScreen } from '../DisputesScreen';

const mock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client }, children);
  };
}

beforeEach(() => {
  mock.reset();
  jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
});

test('opening a dispute posts to /client/disputes', async () => {
  mock.onGet('/client/disputes').reply(200, { data: [] });
  mock.onPost('/client/disputes').reply(201, { data: { id: 1, status: 'open' } });

  render(<DisputesScreen />, { wrapper: makeWrapper() });

  fireEvent.press(await screen.findByText('Ouvrir un litige'));

  fireEvent.changeText(screen.getByPlaceholderText('Sujet'), 'Prestation incomplète');
  fireEvent.changeText(screen.getByPlaceholderText('Décrivez le problème'), 'La prestation a été bâclée et incomplète.');
  fireEvent.press(screen.getByText('Qualité'));
  fireEvent.press(screen.getByText('Envoyer'));

  await waitFor(() => {
    const posted = mock.history.post.find(r => r.url === '/client/disputes');
    expect(posted).toBeTruthy();
    expect(JSON.parse(posted!.data)).toMatchObject({
      subject: 'Prestation incomplète',
      category: 'quality',
    });
  });
});

/**
 * Les champs d'un `FormData`, quelle qu'en soit l'implementation.
 *
 * React Native expose ses parties par `_parts` ; l'environnement de test fournit le `FormData`
 * standard, qui n'a que `entries()`. Lire l'un des deux au hasard rendait un objet vide — et un
 * objet vide compare `undefined` a `undefined` sans rien prouver.
 */
function parties(corps: FormData): [string, unknown][] {
  const brutes = (corps as unknown as { _parts?: [string, unknown][] })._parts;

  return Array.isArray(brutes) ? brutes : Array.from(corps.entries());
}

/**
 * L'API accepte des pieces jointes depuis le 2026-08-27 ; l'application ne les envoyait pas.
 *
 * ON N'ENVOIE DU MULTIPART QUE S'IL Y A QUELQUE CHOSE A JOINDRE : le cas courant garde son corps
 * JSON, et le test au-dessus le prouve encore.
 */
test('une photo choisie part en multipart, dans attachments[]', async () => {
  (ImagePicker.launchImageLibraryAsync as jest.Mock).mockResolvedValueOnce({
    canceled: false,
    assets: [{ uri: 'file:///tmp/degat.jpg' }],
  });

  mock.onGet('/client/disputes').reply(200, { data: [] });
  mock.onPost('/client/disputes').reply(201, { data: { id: 2, status: 'open' } });

  render(<DisputesScreen />, { wrapper: makeWrapper() });

  fireEvent.press(await screen.findByText('Ouvrir un litige'));

  fireEvent.changeText(screen.getByPlaceholderText('Sujet'), 'Degat constate');
  fireEvent.changeText(screen.getByPlaceholderText('Décrivez le problème'), 'Le mur du salon est abime.');
  fireEvent.press(screen.getByText('Qualité'));

  fireEvent.press(screen.getByText('Ajouter une photo'));

  await waitFor(() => expect(ImagePicker.launchImageLibraryAsync).toHaveBeenCalled());

  fireEvent.press(screen.getByText('Envoyer'));

  await waitFor(() => {
    const envoi = mock.history.post.find(r => r.url === '/client/disputes');

    expect(envoi).toBeTruthy();
    expect(envoi!.headers?.['Content-Type']).toBe('multipart/form-data');

    const noms = parties(envoi!.data as FormData).map(([nom]) => nom);

    expect(noms).toContain('attachments[]');
    expect(noms).toContain('subject');
  });
});

/** LE TEMOIN : sans photo, rien ne change — le selecteur n'est meme pas ouvert. */
test('temoin sans photo, le corps reste du JSON', async () => {
  mock.onGet('/client/disputes').reply(200, { data: [] });
  mock.onPost('/client/disputes').reply(201, { data: { id: 3, status: 'open' } });

  render(<DisputesScreen />, { wrapper: makeWrapper() });

  fireEvent.press(await screen.findByText('Ouvrir un litige'));

  fireEvent.changeText(screen.getByPlaceholderText('Sujet'), 'Sans preuve');
  fireEvent.changeText(screen.getByPlaceholderText('Décrivez le problème'), 'Une description assez longue.');
  fireEvent.press(screen.getByText('Qualité'));
  fireEvent.press(screen.getByText('Envoyer'));

  await waitFor(() => {
    const envoi = mock.history.post.find(r => r.url === '/client/disputes');

    expect(envoi).toBeTruthy();
    expect(typeof envoi!.data).toBe('string');
    expect(JSON.parse(envoi!.data)).not.toHaveProperty('attachments');
  });
});
