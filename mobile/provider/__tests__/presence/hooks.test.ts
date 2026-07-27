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
import { act, renderHook, waitFor } from '@testing-library/react-native';
import MockAdapter from 'axios-mock-adapter';
import { apiClient } from '@/api';
import { usePresence } from '@/presence';
import type { PresenceStatus } from '@/presence/types';

jest.mock('@/storage/secureStore');

const mock = new MockAdapter(apiClient);

function presencePayload(status: PresenceStatus) {
  return { data: { status, is_active: status !== 'offline' } };
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

    const { result } = renderHook(() => usePresence());
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

    const { result } = renderHook(() => usePresence());
    await act(async () => {
      await result.current.setPresenceStatus('busy');
    });

    const body = mock.history['post']![0]!.data;
    expect(body ? JSON.parse(body) : {}).not.toHaveProperty('status');
  });

  it('never calls the legacy Phase 11 route', async () => {
    mock.onPost('/provider/presence-v2/online').reply(200, presencePayload('online'));

    const { result } = renderHook(() => usePresence());
    await act(async () => {
      await result.current.goOnline();
    });

    const urls = (mock.history['post'] ?? []).map(c => c.url);
    expect(urls).not.toContain('/provider/presence/online');
  });

  it('hydrates the initial status from GET /provider/presence-v2', async () => {
    mock.reset();
    mock.onGet('/provider/presence-v2').reply(200, presencePayload('on_break'));

    const { result } = renderHook(() => usePresence());

    await waitFor(() => expect(result.current.status).toBe('on_break'));
  });

  it('surfaces the server reason instead of rejecting the promise', async () => {
    mock.onPost('/provider/presence-v2/online').reply(422, {
      error: 'validation_failed',
      errors: { status: ["Heartbeat impossible — provider offline. Appeler goOnline d'abord."] },
    });

    const { result } = renderHook(() => usePresence());
    // Must not throw: the old code left an "Uncaught (in promise)" on every failed tap.
    await act(async () => {
      await result.current.setPresenceStatus('online');
    });

    expect(result.current.error).toContain('Heartbeat impossible');
    expect(result.current.status).toBe('offline');
  });

  it('falls back to a readable message when the API sends no reason', async () => {
    mock.onPost('/provider/presence-v2/busy').reply(500);

    const { result } = renderHook(() => usePresence());
    await act(async () => {
      await result.current.setPresenceStatus('busy');
    });

    expect(result.current.error).toBe('Changement de statut impossible. Réessaie.');
  });
});
