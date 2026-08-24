/**
 * LE TERRAIN SANS RÉSEAU — ce qui attend, et ce qui refuse d'attendre.
 *
 * ── DEUX RÈGLES, ET LA SECONDE EST LA PLUS IMPORTANTE ────────────────────────────────────────
 *
 * Cocher une tâche part dans la file : l'appel porte une VALEUR ABSOLUE (`done` / `pending`), le
 * rejouer deux fois donne le même résultat qu'une fois.
 *
 * Clôturer n'y entre PAS, et ce n'est pas un oubli. La clôture consomme un code de fin à usage
 * unique et déclenche l'encaissement : rejouée à la reconnexion, elle échouerait sur un code déjà
 * consommé — après avoir laissé croire au prestataire qu'il avait terminé et quitté les lieux.
 */
import { renderHook, act, waitFor } from '@testing-library/react-native';
import React from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AsyncStorage from '@react-native-async-storage/async-storage';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

const reseau = { isConnected: false };

jest.mock('@react-native-community/netinfo', () => ({
  fetch: jest.fn(async () => reseau),
  addEventListener: jest.fn(() => () => undefined),
  default: { fetch: jest.fn(async () => reseau), addEventListener: jest.fn(() => () => undefined) },
}));

jest.mock('@/tracking', () => ({ readScanPosition: jest.fn() }));
jest.mock('@/screens/onboarding/documentPicker', () => ({ pickImage: jest.fn() }));
jest.mock('@/realtime', () => ({ useChannel: () => undefined }));

import { offlineQueue, apiClient } from '@brio/shared';
import { useToggleMissionChecklistItem } from '@/missions/onsite';
import { useMissionLifecycle } from '@/missions/hooks';

function enveloppe({ children }: { children: React.ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });

  return React.createElement(QueryClientProvider, { client }, children);
}

beforeEach(async () => {
  reseau.isConnected = false;
  await AsyncStorage.clear();
});

describe('Le terrain sans réseau', () => {
  it('met la case cochée dans la file au lieu de la perdre', async () => {
    const { result } = renderHook(() => useToggleMissionChecklistItem(42), { wrapper: enveloppe });

    await act(async () => {
      result.current.mutate({ itemId: 7, done: true });
    });

    await waitFor(async () => {
      expect(await offlineQueue.getAll()).toHaveLength(1);
    });

    const [action] = await offlineQueue.getAll();
    expect(action?.url).toBe('/provider/missions/42/checklist/7');
    // La valeur est ABSOLUE : c'est ce qui rend le renvoi sûr.
    expect(action?.body).toEqual({ status: 'done' });
  });

  it('refuse la clôture et dit pourquoi', async () => {
    const { result } = renderHook(() => useMissionLifecycle(42), { wrapper: enveloppe });

    await act(async () => {
      result.current.mutate({ action: 'complete', code: '123456' });
    });

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(result.current.error?.message).toContain('connexion');
    // Et surtout : elle n'est PAS partie dans la file.
    expect(await offlineQueue.getAll()).toHaveLength(0);
  });

  /**
   * LE TÉMOIN POSITIF : avec du réseau, la clôture n'est pas retenue par ce garde.
   *
   * L'appel est BOUCHONNÉ. Sans cela le test attendait un vrai aller-retour HTTP qui ne
   * répond jamais : seul il finissait par expirer a temps, mais lancé après les deux autres
   * il restait `isPending` et tombait. Un témoin ne doit pas dépendre du réseau.
   */
  it('laisse passer la clôture dès qu’il y a du réseau', async () => {
    reseau.isConnected = true;

    const appel = jest.spyOn(apiClient, 'post').mockResolvedValue({ data: { ok: true } } as never);

    try {
      const { result } = renderHook(() => useMissionLifecycle(42), { wrapper: enveloppe });

      await act(async () => {
        result.current.mutate({ action: 'complete', code: '123456' });
      });

      await waitFor(() => expect(result.current.isPending).toBe(false));

      // Le garde n'a pas leve, et l'appel est parti.
      expect(result.current.error).toBeNull();
      expect(appel).toHaveBeenCalledWith('/provider/missions/42/complete', expect.anything());
    } finally {
      appel.mockRestore();
    }
  });
});
