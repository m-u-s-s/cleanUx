/**
 * usePresence — contract tests against the Presence v2 API.
 *
 * Regression: this suite previously asserted only that axios-mock-adapter works, and it
 * enshrined the broken contract (POST /provider/presence/online, `{ status }` on
 * /heartbeat). Both produced the errors seen in the app: 403 "Vous devez être prestataire"
 * from the legacy Phase 11 route, and 422 from a heartbeat sent while still offline.
 *
 * The real contract is one endpoint per transition, no `status` in any payload.
 */
import React from 'react';
import { act, renderHook, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';
import { apiClient } from '@/api';
import { usePresence, usePresenceHeartbeat } from '@/presence';
import type { PresenceStatus } from '@/presence/types';

// Le statut vit désormais dans le cache React Query (source unique partagée par la pilule et le
// toggle). Ses notifications passent par un `setTimeout(0)` par défaut, qui délivrerait la mise
// à jour hors de toute portée `act()` ; on les rend synchrones dans ce fichier.
notifyManager.setScheduler((callback) => callback());

jest.mock('@/storage/secureStore');

const mock = new MockAdapter(apiClient);

function presencePayload(status: PresenceStatus) {
  return { data: { status, is_active: status !== 'offline' } };
}

/** Un client neuf par test : le cache de présence ne doit jamais fuiter d'un cas à l'autre. */
function makeWrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return ({ children }: { children: React.ReactNode }) =>
    React.createElement(QueryClientProvider, { client }, children);
}

beforeEach(() => {
  mock.reset();
  mock.onGet('/provider/presence-v2').reply(200, presencePayload('offline'));
});

afterAll(() => mock.restore());

describe('usePresence — v2 transition endpoints', () => {
  const cases: Array<[PresenceStatus, string]> = [
    ['online', '/provider/presence-v2/online'],
    ['busy', '/provider/presence-v2/busy'],
    ['on_break', '/provider/presence-v2/break'],
    ['offline', '/provider/presence-v2/offline'],
  ];

  it.each(cases)('setPresenceStatus(%s) posts to %s', async (status, url) => {
    mock.onPost(url).reply(200, presencePayload(status));

    const { result } = renderHook(() => usePresence(), { wrapper: makeWrapper() });
    await act(async () => {
      await result.current.setPresenceStatus(status);
    });

    const posts = mock.history['post'] ?? [];
    expect(posts).toHaveLength(1);
    expect(posts[0]!.url).toBe(url);
    expect(result.current.status).toBe(status);
  });

  it('never posts a `status` field — the server ignores it', async () => {
    mock.onPost('/provider/presence-v2/busy').reply(200, presencePayload('busy'));

    const { result } = renderHook(() => usePresence(), { wrapper: makeWrapper() });
    await act(async () => {
      await result.current.setPresenceStatus('busy');
    });

    const body = mock.history['post']![0]!.data;
    expect(body ? JSON.parse(body) : {}).not.toHaveProperty('status');
  });

  it('never calls the legacy Phase 11 route', async () => {
    mock.onPost('/provider/presence-v2/online').reply(200, presencePayload('online'));

    const { result } = renderHook(() => usePresence(), { wrapper: makeWrapper() });
    await act(async () => {
      await result.current.goOnline();
    });

    const urls = (mock.history['post'] ?? []).map(c => c.url);
    expect(urls).not.toContain('/provider/presence/online');
  });

  it('hydrates the initial status from GET /provider/presence-v2', async () => {
    mock.reset();
    mock.onGet('/provider/presence-v2').reply(200, presencePayload('on_break'));

    const { result } = renderHook(() => usePresence(), { wrapper: makeWrapper() });

    await waitFor(() => expect(result.current.status).toBe('on_break'));
  });

  it('surfaces the server reason instead of rejecting the promise', async () => {
    mock.onPost('/provider/presence-v2/online').reply(422, {
      error: 'validation_failed',
      errors: { status: ["Heartbeat impossible — provider offline. Appeler goOnline d'abord."] },
    });

    const { result } = renderHook(() => usePresence(), { wrapper: makeWrapper() });
    // Must not throw: the old code left an "Uncaught (in promise)" on every failed tap.
    await act(async () => {
      await result.current.setPresenceStatus('online');
    });

    expect(result.current.error).toContain('Heartbeat impossible');
    expect(result.current.status).toBe('offline');
  });

  it('falls back to a readable message when the API sends no reason', async () => {
    mock.onPost('/provider/presence-v2/busy').reply(500);

    const { result } = renderHook(() => usePresence(), { wrapper: makeWrapper() });
    await act(async () => {
      await result.current.setPresenceStatus('busy');
    });

    expect(result.current.error).toBe('Changement de statut impossible. Réessaie.');
  });
});

describe('usePresenceHeartbeat — un seul minuteur, hors des hooks de lecture', () => {
  const HEARTBEAT = '/provider/presence-v2/heartbeat';

  function beats() {
    return (mock.history['post'] ?? []).filter(c => c.url === HEARTBEAT);
  }

  // `await act` après l'avance du temps : la requête part derrière un intercepteur asynchrone
  // (lecture du jeton), donc l'historique n'est alimenté qu'une fois les microtâches vidées.
  async function advance(ms: number) {
    await act(async () => {
      jest.advanceTimersByTime(ms);
    });
  }

  beforeEach(() => {
    mock.onPost(HEARTBEAT).reply(200, { ok: true });
    jest.useFakeTimers();
  });

  afterEach(() => jest.useRealTimers());

  it('bat une fois toutes les 30 s quand le prestataire est en ligne', async () => {
    mock.reset();
    mock.onGet('/provider/presence-v2').reply(200, presencePayload('online'));
    mock.onPost(HEARTBEAT).reply(200, { ok: true });

    renderHook(() => usePresenceHeartbeat(), { wrapper: makeWrapper() });
    await waitFor(() => expect(mock.history['get']).toHaveLength(1));

    await advance(30_000);
    expect(beats()).toHaveLength(1);

    await advance(60_000);
    expect(beats()).toHaveLength(3);
  });

  it('ne bat jamais tant que le prestataire est hors ligne', async () => {
    renderHook(() => usePresenceHeartbeat(), { wrapper: makeWrapper() });
    await waitFor(() => expect(mock.history['get']).toHaveLength(1));

    await advance(120_000);

    expect(beats()).toHaveLength(0);
  });
});
