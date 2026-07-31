/**
 * Ce qui part RÉELLEMENT sur le réseau quand on valide un code.
 *
 * Les tests d'écran simulent ces hooks : ils prouvent que l'écran leur passe bien une position,
 * pas que la position atteint le serveur. Un hook qui l'accepterait puis l'oublierait laisserait
 * l'écran vert, le serveur refuserait toutes les validations en production, et rien dans la suite
 * n'aurait bougé.
 *
 * Le serveur exige `lat`/`lng` — pas `latitude`/`longitude`. Cette confusion exacte a déjà coûté
 * cher ici : `useSendPing` a longtemps envoyé les mauvais noms de champs sur une route qui
 * n'existait pas, et aucun test ne l'a vu.
 */
import React from 'react';
import { renderHook, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const mockPost = jest.fn();

jest.mock('@/api', () => ({
  apiClient: { post: (...args: unknown[]) => mockPost(...args) },
  ApiError: class ApiError extends Error {},
}));

import { useConfirmPresence, useCompleteByQr } from '@/tracking';

function wrapper({ children }: { children: React.ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

const ON_SITE = { lat: 50.8467, lng: 4.3525, accuracy_m: 12, mocked: false };

beforeEach(() => {
  mockPost.mockReset();
  mockPost.mockResolvedValue({ data: { data: { id: 1 }, mission_started: false } });
});

describe('Charge envoyée au serveur', () => {
  it('joint la position à la confirmation de présence', async () => {
    const { result } = renderHook(() => useConfirmPresence(42), { wrapper });
    result.current.mutate({ code: '482951', position: ON_SITE });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockPost).toHaveBeenCalledWith('/provider/tracking/42/confirm-presence', {
      code: '482951',
      lat: 50.8467,
      lng: 4.3525,
      accuracy_m: 12,
      mocked: false,
    });
  });

  it('joint la position à la clôture', async () => {
    const { result } = renderHook(() => useCompleteByQr(4), { wrapper });
    result.current.mutate({ code: '731204', position: ON_SITE });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockPost).toHaveBeenCalledWith('/provider/missions/4/complete-by-qr', {
      code: '731204',
      lat: 50.8467,
      lng: 4.3525,
      accuracy_m: 12,
      mocked: false,
    });
  });

  /**
   * Position indisponible : on n'envoie AUCUNE clé plutôt que des nulls. Le serveur distingue
   * « pas de position » de « position nulle », et c'est lui qui décide si l'absence est
   * recevable.
   */
  it('n’invente rien quand le relevé a échoué', async () => {
    const { result } = renderHook(() => useConfirmPresence(42), { wrapper });
    result.current.mutate({ code: '482951', position: null });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockPost).toHaveBeenCalledWith('/provider/tracking/42/confirm-presence', { code: '482951' });
  });

  it('n’invente rien non plus à la clôture', async () => {
    const { result } = renderHook(() => useCompleteByQr(4), { wrapper });
    result.current.mutate({ code: '731204', position: null });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockPost).toHaveBeenCalledWith('/provider/missions/4/complete-by-qr', { code: '731204' });
  });

  /** Une précision inconnue se transmet telle quelle : le serveur retombe sur son rayon de base. */
  it('transmet une précision inconnue sans la maquiller', async () => {
    const { result } = renderHook(() => useConfirmPresence(42), { wrapper });
    result.current.mutate({ code: '482951', position: { ...ON_SITE, accuracy_m: null } });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockPost).toHaveBeenCalledWith(
      '/provider/tracking/42/confirm-presence',
      expect.objectContaining({ accuracy_m: null }),
    );
  });

  /** L'aveu de simulation doit voyager : c'est le serveur qui refuse, pas l'application. */
  it('fait suivre une position signalée comme simulée', async () => {
    const { result } = renderHook(() => useCompleteByQr(4), { wrapper });
    result.current.mutate({ code: '731204', position: { ...ON_SITE, mocked: true } });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockPost).toHaveBeenCalledWith(
      '/provider/missions/4/complete-by-qr',
      expect.objectContaining({ mocked: true }),
    );
  });
});
