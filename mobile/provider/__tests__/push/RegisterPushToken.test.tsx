/**
 * LE TÉLÉPHONE DU PRESTATAIRE EST-IL SEULEMENT ENREGISTRÉ ?
 *
 * Le serveur n'envoie une notification qu'aux appareils qu'il connaît. L'application cliente montait
 * `useRegisterPushToken()` depuis le début ; l'application prestataire, NON — celle qui en a le plus
 * besoin, puisque c'est elle qui reçoit des courses à vingt secondes d'échéance. Rien ne pouvait le
 * signaler : `tsc` compile, le hook existe, et le canal de repli par sondage cachait le trou en
 * développement. Sur un téléphone verrouillé, il n'y a pas de sondage.
 *
 * DEUX CHOSES SONT VÉRIFIÉES ICI, et la seconde est celle qu'on oublie :
 *  1. l'enregistrement PART ;
 *  2. il part sur `/provider/devices/register`, pas sur celui du client. Les deux routes acceptent
 *     n'importe quel compte authentifié, donc l'erreur n'aurait produit aucun échec visible — juste
 *     une flotte entière rangée du mauvais côté, introuvable par un filtre d'espace.
 */
import React from 'react';
import { render, waitFor } from '@testing-library/react-native';
import { Text } from 'react-native';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('expo-notifications', () => ({
  requestPermissionsAsync: jest.fn().mockResolvedValue({ status: 'granted' }),
  getExpoPushTokenAsync: jest.fn().mockResolvedValue({ data: 'ExponentPushToken[abc]' }),
}));

// Le hook ne fait rien tant que personne n'est connecté : c'est voulu, on n'enregistre pas
// l'appareil d'un visiteur. Le test porte sur ce qui se passe APRÈS la connexion.
jest.mock('@/auth', () => ({ useAuth: () => ({ isAuthenticated: true }) }));

import { apiClient, setAppAudience } from '@/api';
import { useRegisterPushToken } from '@/push';

const mock = new MockAdapter(apiClient);

function Sonde() {
  useRegisterPushToken();
  return <Text>sonde</Text>;
}

describe('Enregistrement du jeton push', () => {
  beforeEach(() => {
    mock.reset();
    mock.onPost(/\/devices\/register$/).reply(201, { ok: true });
  });

  it("enregistre l'appareil sur l'espace prestataire", async () => {
    setAppAudience('provider');
    render(<Sonde />);

    await waitFor(() => expect(mock.history.post).toHaveLength(1));
    expect(mock.history.post[0]!.url).toBe('/provider/devices/register');
    expect(JSON.parse(mock.history.post[0]!.data).token).toBe('ExponentPushToken[abc]');
  });

  it("retombe sur l'espace client quand l'application ne s'est pas déclarée", async () => {
    // Le parc déjà installé n'envoie pas encore l'en-tête : il ne doit pas cesser d'être joignable.
    setAppAudience(null);
    render(<Sonde />);

    await waitFor(() => expect(mock.history.post).toHaveLength(1));
    expect(mock.history.post[0]!.url).toBe('/client/devices/register');
  });
});
