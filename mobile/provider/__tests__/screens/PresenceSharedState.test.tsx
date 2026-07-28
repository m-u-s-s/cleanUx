/**
 * Le dashboard carte-first monte DEUX consommateurs de présence en même temps :
 * `PresencePill` (posée sur la carte, seul indicateur de statut de la page) et
 * `PresenceToggle` (dans le sheet, que gorhom ne démonte jamais, même fermé).
 *
 * Chaque test existant mocke l'autre moitié — `PresencePill.test.tsx` mocke `usePresence`,
 * `Dashboard.interaction.test.tsx` mocke le sheet entier. C'est exactement pour ça qu'un état
 * de présence dupliqué a pu passer la revue : les deux composants tenaient chacun leur
 * `useState`, donc changer de statut dans le sheet ne mettait jamais à jour la pilule, et deux
 * hydratations + deux battements de cœur partaient en parallèle.
 *
 * Ce fichier est le seul endroit où les deux composants vivent dans le MÊME arbre.
 */
import React from 'react';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

// Notifications React Query synchrones dans ce fichier : sinon le `setTimeout(0)` par défaut
// délivre la mise à jour du cache hors de toute portée `act()` (« not wrapped in act »).
notifyManager.setScheduler((callback) => callback());

jest.mock('@/storage/secureStore');

import { apiClient } from '@/api';
import { PresencePill } from '@/screens/components/PresencePill';
import { PresenceToggle } from '@/screens/components/PresenceToggle';

const STATUS_ENDPOINT = '/provider/presence-v2';
const HEARTBEAT_ENDPOINT = '/provider/presence-v2/heartbeat';

const apiMock = new MockAdapter(apiClient);

function renderBothConsumers() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <PresencePill onPress={jest.fn()} />
      <PresenceToggle />
    </QueryClientProvider>,
  );
}

const pillLabel = (text: string) => within(screen.getByTestId('presence-pill')).getByText(text);

beforeEach(() => {
  apiMock.reset();
  apiMock.onGet(STATUS_ENDPOINT).reply(200, { data: { status: 'offline', is_active: false } });
  apiMock.onPost(HEARTBEAT_ENDPOINT).reply(200, { ok: true });
});

afterAll(() => apiMock.restore());

describe('Présence — état partagé entre la pilule et le sheet', () => {
  it('répercute sur la pilule un changement de statut fait dans le sheet', async () => {
    apiMock.onPost('/provider/presence-v2/busy').reply(200, { data: { status: 'busy', is_active: true } });

    renderBothConsumers();
    await waitFor(() => expect(pillLabel('Hors ligne')).toBeTruthy());

    // Tant que le statut est 'offline', « Occupé » n'existe qu'une fois à l'écran : c'est le
    // bouton du toggle (ni l'en-tête du toggle ni la pilule ne portent encore ce libellé).
    fireEvent.press(screen.getByText('Occupé'));

    await waitFor(() => expect(pillLabel('Occupé')).toBeTruthy());
  });

  it("n'hydrate le statut qu'une seule fois pour les deux consommateurs", async () => {
    apiMock.reset();
    apiMock.onGet(STATUS_ENDPOINT).reply(200, { data: { status: 'online', is_active: true } });

    renderBothConsumers();
    await waitFor(() => expect(pillLabel('En ligne')).toBeTruthy());

    const hydrations = apiMock.history['get']!.filter(c => c.url === STATUS_ENDPOINT);
    expect(hydrations).toHaveLength(1);
  });

  it("n'émet aucun battement de cœur depuis les composants d'affichage", async () => {
    jest.useFakeTimers();
    apiMock.reset();
    apiMock.onGet(STATUS_ENDPOINT).reply(200, { data: { status: 'online', is_active: true } });

    try {
      renderBothConsumers();
      await waitFor(() => expect(pillLabel('En ligne')).toBeTruthy());

      // `await act` est indispensable : la requête part derrière un intercepteur asynchrone
      // (lecture du jeton), donc l'historique d'axios-mock-adapter n'est alimenté qu'une fois
      // les microtâches vidées. Sans ça, l'assertion passerait même avec des intervalles actifs.
      await act(async () => {
        jest.advanceTimersByTime(60_000);
      });

      // Le battement de cœur appartient à un seul point de montage (usePresenceHeartbeat,
      // câblé dans TabNavigator) : deux consommateurs d'affichage ne doivent en produire aucun,
      // et surtout pas un intervalle chacun.
      const beats = (apiMock.history['post'] ?? []).filter(c => c.url === HEARTBEAT_ENDPOINT);
      expect(beats).toHaveLength(0);
    } finally {
      jest.useRealTimers();
    }
  });
});
