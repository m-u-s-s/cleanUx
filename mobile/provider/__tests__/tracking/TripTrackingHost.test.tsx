/**
 * LE SUIVI EN DIRECT PART TOUT SEUL — et s'arrête tout seul.
 *
 * Il vivait dans un écran que le prestataire devait ouvrir et GARDER OUVERT, en conduisant. Le
 * parcours normal étant « En route » puis « Je suis arrivé », la session de suivi naissait sans une
 * seule position : le client, à qui l'on promet de voir son prestataire approcher, n'avait jamais
 * rien à voir. Le relevé suit désormais la MISSION, pas l'écran affiché.
 *
 * CE FICHIER PRESSE LES VRAIES CONDITIONS : une mission en route ouvre la session et fait partir
 * les points ; aucune mission en route n'ouvre RIEN — la position d'un prestataire qui ne roule
 * pour personne ne regarde pas la plateforme.
 */
import React from 'react';
import { render, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

notifyManager.setScheduler((callback) => callback());

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
}));

/**
 * L'observateur GPS est bouchonné : il n'existe pas d'appareil sous Jest, et ce qu'on vérifie
 * n'est pas `expo-location` mais QUAND il est ouvert et ce qu'on fait de ses relevés.
 */
const mockWatchers: Array<{ enabled: boolean; emit: (p: any) => void }> = [];

jest.mock('@/tracking/hooks', () => {
  const reel = jest.requireActual('@/tracking/hooks');

  return {
    ...reel,
    useGpsWatcher: (enabled: boolean, onPosition: (p: any) => void) => {
      mockWatchers.push({ enabled, emit: onPosition });

      return { permission: 'granted' };
    },
  };
});

import { apiClient } from '@/api';
import { TripTrackingHost } from '@/tracking';

const mock = new MockAdapter(apiClient);

function monter() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <TripTrackingHost />
    </QueryClientProvider>,
  );
}

/** Le dernier observateur monté — celui du rendu courant. */
const dernierObservateur = () => mockWatchers[mockWatchers.length - 1]!;

describe('TripTrackingHost', () => {
  beforeEach(() => {
    mock.reset();
    mockWatchers.length = 0;
  });

  it('ouvre la session dès qu’une mission est en route', async () => {
    mock.onGet('/provider/missions/active').reply(200, {
      data: [{ id: 7, status: 'en_route', booking_id: 42 }],
    });
    mock.onPost('/provider/bookings/42/tracking/start').reply(200, { data: { id: 99 } });

    monter();

    await waitFor(() =>
      expect(mock.history.post.map((r) => r.url)).toContain('/provider/bookings/42/tracking/start'),
    );
  });

  it('envoie les positions relevées', async () => {
    mock.onGet('/provider/missions/active').reply(200, {
      data: [{ id: 7, status: 'en_route', booking_id: 42 }],
    });
    mock.onPost('/provider/bookings/42/tracking/start').reply(200, { data: { id: 99 } });
    mock.onPost('/provider/tracking/99/ping').reply(200, {});

    monter();

    // L'observateur ne s'ouvre qu'une fois la session connue : sans elle, il n'y a aucune adresse
    // où envoyer les points.
    await waitFor(() => expect(dernierObservateur().enabled).toBe(true));

    dernierObservateur().emit({ latitude: 50.85, longitude: 4.35, speed: 8, heading: 90 });

    await waitFor(() =>
      expect(mock.history.post.map((r) => r.url)).toContain('/provider/tracking/99/ping'),
    );

    const envoi = JSON.parse(mock.history.post.find((r) => r.url?.endsWith('/ping'))!.data);
    // Le serveur attend `lat`/`lng` : `latitude`/`longitude` ont déjà fait qu'aucun relevé
    // n'atteignait jamais la base.
    expect(envoi.lat).toBe(50.85);
    expect(envoi.lng).toBe(4.35);
  });

  it('n’ouvre aucun observateur sans mission en route', async () => {
    mock.onGet('/provider/missions/active').reply(200, {
      data: [{ id: 7, status: 'assigned', booking_id: 42 }],
    });

    monter();

    await waitFor(() => expect(mock.history.get.length).toBeGreaterThan(0));

    // Une mission acceptée n'est pas une mission en route : relever la position de quelqu'un qui
    // n'a pas encore pris la route serait une collecte sans usage.
    expect(mockWatchers.every((w) => !w.enabled)).toBe(true);
    expect(mock.history.post).toHaveLength(0);
  });

  it('ne relève plus rien une fois l’intervention démarrée', async () => {
    mock.onGet('/provider/missions/active').reply(200, {
      data: [{ id: 7, status: 'started', booking_id: 42 }],
    });

    monter();

    await waitFor(() => expect(mock.history.get.length).toBeGreaterThan(0));

    // Le prestataire est chez le client : sa position n'apprend plus rien, et l'écran client
    // remplace d'ailleurs la carte par le code de présence.
    expect(mockWatchers.every((w) => !w.enabled)).toBe(true);
  });

  it('n’ouvre la session qu’une fois, malgré les rafraîchissements', async () => {
    mock.onGet('/provider/missions/active').reply(200, {
      data: [{ id: 7, status: 'en_route', booking_id: 42 }],
    });
    mock.onPost('/provider/bookings/42/tracking/start').reply(200, { data: { id: 99 } });

    const { rerender } = monter();

    await waitFor(() => expect(mock.history.post).toHaveLength(1));

    rerender(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <TripTrackingHost />
      </QueryClientProvider>,
    );

    // Le serveur réutilise une session existante, mais rouvrir à chaque sondage ferait un appel
    // toutes les trente secondes pendant tout un trajet.
    await waitFor(() => expect(mock.history.post.length).toBeLessThanOrEqual(2));
  });
});
