/**
 * « Je suis arrivé » fait avancer les DEUX machines à états.
 *
 * La mission suit `en_route → arrived → started` ; la session de suivi suit
 * `enroute → arrived → in_mission`. N'en avancer qu'une laissait le prestataire devant un badge
 * « En route » alors qu'il venait d'annoncer son arrivée — le geste paraissait sans effet.
 *
 * L'arrivée de la mission est SOUPLE : un second passage la trouve déjà `arrived` et le serveur
 * refuse la transition. Ce refus ne doit pas empêcher la preuve de présence, qui est l'objet du
 * geste.
 */
import React from 'react';
import { renderHook, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const mockPost = jest.fn();

jest.mock('@/api', () => ({
  apiClient: { post: (...args: unknown[]) => mockPost(...args) },
  ApiError: class ApiError extends Error {},
}));

import { useArriveOnSite } from '@/tracking';
import { missionStatusLabel } from '@/missions';

function wrapper({ children }: { children: React.ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

beforeEach(() => mockPost.mockReset());

describe('Arrivée sur site', () => {
  it('annonce l’arrivée de la mission ET démarre l’intervention', async () => {
    mockPost.mockImplementation((url: string) => {
      if (url.includes('/arrive')) return Promise.resolve({ data: { ok: true } });
      if (url.includes('/tracking/start')) return Promise.resolve({ data: { data: { id: 9, status: 'arrived' } } });

      return Promise.resolve({ data: { data: { id: 9, status: 'in_mission' } } });
    });

    const { result } = renderHook(() => useArriveOnSite(7, 4), { wrapper });
    result.current.mutate();

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockPost).toHaveBeenCalledWith('/provider/missions/4/arrive');
    expect(mockPost).toHaveBeenCalledWith('/provider/bookings/7/tracking/start');
    expect(mockPost).toHaveBeenCalledWith('/provider/tracking/9/in-mission');
    expect(result.current.data?.status).toBe('in_mission');
  });

  /** Le cœur de la souplesse : un second passage ne doit pas bloquer le scan. */
  it('poursuit vers la présence même si la mission refuse la transition', async () => {
    mockPost.mockImplementation((url: string) => {
      if (url.includes('/arrive')) return Promise.reject(new Error('422'));
      if (url.includes('/tracking/start')) return Promise.resolve({ data: { data: { id: 9, status: 'in_mission' } } });

      return Promise.resolve({ data: { data: { id: 9, status: 'in_mission' } } });
    });

    const { result } = renderHook(() => useArriveOnSite(7, 4), { wrapper });
    result.current.mutate();

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.id).toBe(9);
  });

  /** Sans mission liée, le suivi doit fonctionner seul. */
  it('n’appelle pas la mission quand il n’y en a pas', async () => {
    mockPost.mockResolvedValue({ data: { data: { id: 9, status: 'in_mission' } } });

    const { result } = renderHook(() => useArriveOnSite(7, null), { wrapper });
    result.current.mutate();

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockPost).not.toHaveBeenCalledWith(expect.stringContaining('/arrive'));
  });
});

describe('Libellés de statut', () => {
  /** Le badge affichait l'identifiant technique anglais dans une application française. */
  it('traduit les statuts en français', () => {
    expect(missionStatusLabel('arrived')).toBe('Sur place');
    expect(missionStatusLabel('en_route')).toBe('En route');
    expect(missionStatusLabel('started')).toBe('En cours');
  });

  /** Un statut inconnu doit rester visible plutôt que disparaître. */
  it('laisse passer un statut qu’il ne connaît pas', () => {
    expect(missionStatusLabel('etat_inedit')).toBe('etat_inedit');
  });
});
