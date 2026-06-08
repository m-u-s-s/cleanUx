import React from 'react';
import { apiClient } from '@/api';
import { useMissionLifecycle } from '@/missions';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react-native';
import MockAdapter from 'axios-mock-adapter';
jest.mock('@/storage/secureStore');
jest.mock('@/realtime', () => ({ useChannel: () => {} }));

describe('Mission API', () => {
  let mock: MockAdapter;
  beforeEach(() => {
    mock = new MockAdapter(apiClient);
  });
  afterEach(() => mock.restore());

  it('fetches mission inbox', async () => {
    mock
      .onGet('/provider/assignments/inbox')
      .reply(200, { data: [{ id: 1, service_name: 'Nettoyage' }] });
    const res = await apiClient.get('/provider/assignments/inbox');
    expect(res.data.data[0].service_name).toBe('Nettoyage');
  });

  it('accepts assignment', async () => {
    mock.onPost('/provider/assignments/1/accept').reply(200);
    expect((await apiClient.post('/provider/assignments/1/accept')).status).toBe(200);
  });

  it('declines assignment', async () => {
    mock.onPost('/provider/assignments/1/decline').reply(200);
    expect((await apiClient.post('/provider/assignments/1/decline')).status).toBe(200);
  });

  it('starts mission lifecycle', async () => {
    mock.onPost('/provider/missions/1/start').reply(200);
    expect((await apiClient.post('/provider/missions/1/start')).status).toBe(200);
  });

  // E2 — the end code collected in the UI must reach the server as `end_code`.
  it('sends end_code to the server when completing with a code', async () => {
    let sentBody: any = null;
    mock.onPost('/provider/missions/1/complete').reply((config) => {
      sentBody = JSON.parse(config.data ?? '{}');
      return [200, { ok: true, status: 'completed' }];
    });

    const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(QueryClientProvider, { client: qc }, children);

    const { result } = renderHook(() => useMissionLifecycle(1), { wrapper });
    result.current.mutate({ action: 'complete', code: '482951' });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(sentBody).toEqual({ end_code: '482951' });
  });

  it('omits the body when completing without a code (backward compatible)', async () => {
    let rawData: unknown = 'unset';
    mock.onPost('/provider/missions/1/complete').reply((config) => {
      rawData = config.data;
      return [200, { ok: true, status: 'completed' }];
    });

    const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(QueryClientProvider, { client: qc }, children);

    const { result } = renderHook(() => useMissionLifecycle(1), { wrapper });
    result.current.mutate('complete');

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(rawData).toBeUndefined();
  });
});
