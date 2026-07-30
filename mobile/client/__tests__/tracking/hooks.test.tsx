/**
 * Les crochets de suivi traduisent la charge RÉELLE du serveur.
 *
 * Défaut d'origine : `useTrackingSession` renvoyait l'enveloppe `{ data: … }` entière, si bien que
 * `session.status` et `session.eta_minutes` valaient toujours `undefined` ; et la trace arrivait
 * en `lat`/`lng` alors que les écrans lisaient `latitude`/`longitude`. La carte du suivi ne
 * pouvait donc jamais se centrer, quelle que soit la mission.
 *
 * Les charges ci-dessous sont copiées de `TripTrackingController` — pas reconstruites de mémoire.
 * C'est la seule protection qui vaille : un test écrit contre une forme imaginée aurait validé
 * exactement le bug qu'il devait attraper.
 */
import React from 'react';
import { renderHook, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const mockGet = jest.fn();

jest.mock('@/api', () => ({ apiClient: { get: (...args: unknown[]) => mockGet(...args) } }));
jest.mock('@/realtime', () => ({ useChannel: jest.fn() }));

import { useTrackingSession, useTrackingTrail } from '@/tracking';

/** Réponse littérale de `GET /client/bookings/{id}/tracking`. */
const SESSION_PAYLOAD = {
  data: {
    code: 'TRK-ABC123',
    status: 'enroute',
    destination: { lat: 50.8503, lng: 4.3517 },
    provider: { lat: 50.8402, lng: 4.3401, speed_mps: 11.11 },
    eta_seconds: 420,
    eta_minutes: 7,
    arrived_at: null,
    in_mission_at: null,
    last_ping_at: '2026-07-30T08:12:00.000000Z',
  },
};

/** Réponse littérale de `GET /client/bookings/{id}/tracking/trail`. */
const TRAIL_PAYLOAD = {
  data: [
    { lat: 50.8300, lng: 4.3300, eta_seconds: 600, distance_to_dest_m: 3200, at: '2026-07-30T08:05:00.000000Z' },
    { lat: 50.8402, lng: 4.3401, eta_seconds: 420, distance_to_dest_m: 2100, at: '2026-07-30T08:12:00.000000Z' },
  ],
};

function wrapper({ children }: { children: React.ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

beforeEach(() => mockGet.mockReset());

describe('useTrackingSession', () => {
  it("déballe l'enveloppe data du serveur", async () => {
    mockGet.mockResolvedValue({ data: SESSION_PAYLOAD });

    const { result } = renderHook(() => useTrackingSession(7), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    // Le défaut rapporté : ces deux champs valaient `undefined`, la pastille d'ETA ne
    // s'affichait donc jamais et le badge de statut restait vide.
    expect(result.current.data?.status).toBe('enroute');
    expect(result.current.data?.eta_minutes).toBe(7);
    expect(result.current.data?.code).toBe('TRK-ABC123');
  });

  it('traduit la position du prestataire en latitude/longitude', async () => {
    mockGet.mockResolvedValue({ data: SESSION_PAYLOAD });

    const { result } = renderHook(() => useTrackingSession(7), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.provider).toEqual({ latitude: 50.8402, longitude: 4.3401, speed: 11.11 });
    expect(result.current.data?.destination).toEqual({ latitude: 50.8503, longitude: 4.3517 });
  });

  /** Une réservation confirmée mais non démarrée : absence légitime, pas une erreur. */
  it('rend null quand aucune session n’est ouverte', async () => {
    mockGet.mockResolvedValue({ data: { data: null } });

    const { result } = renderHook(() => useTrackingSession(7), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data).toBeNull();
  });

  /**
   * Une réservation non géocodée n'a pas de destination. Sans cette garde, `{lat: null}` se
   * transformerait en `{latitude: 0}` — soit un point au large du golfe de Guinée.
   */
  it('ne fabrique pas de coordonnées à partir de champs nuls', async () => {
    mockGet.mockResolvedValue({
      data: { data: { ...SESSION_PAYLOAD.data, destination: { lat: null, lng: null } } },
    });

    const { result } = renderHook(() => useTrackingSession(7), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.destination).toBeNull();
    expect(result.current.data?.provider).not.toBeNull();
  });

  it("interroge la bonne route", async () => {
    mockGet.mockResolvedValue({ data: SESSION_PAYLOAD });

    const { result } = renderHook(() => useTrackingSession(7), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockGet).toHaveBeenCalledWith('/client/bookings/7/tracking');
  });
});

describe('useTrackingTrail', () => {
  it('traduit chaque point vers le vocabulaire des cartes', async () => {
    mockGet.mockResolvedValue({ data: TRAIL_PAYLOAD });

    const { result } = renderHook(() => useTrackingTrail(7), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data).toHaveLength(2);
    // Le défaut : les écrans lisaient `latitude`, le serveur envoyait `lat`.
    expect(result.current.data?.[1]).toEqual({
      latitude: 50.8402,
      longitude: 4.3401,
      eta_seconds: 420,
      distance_to_dest_m: 2100,
      recorded_at: '2026-07-30T08:12:00.000000Z',
    });
  });

  /** L'ordre porte du sens : les écrans prennent le dernier élément comme position courante. */
  it('conserve l’ordre chronologique du serveur', async () => {
    mockGet.mockResolvedValue({ data: TRAIL_PAYLOAD });

    const { result } = renderHook(() => useTrackingTrail(7), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    const points = result.current.data ?? [];
    expect(new Date(points[0].recorded_at).getTime())
      .toBeLessThan(new Date(points[1].recorded_at).getTime());
  });

  it('rend un tableau vide sans session', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } });

    const { result } = renderHook(() => useTrackingTrail(7), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data).toEqual([]);
  });
});
