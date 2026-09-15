import React from 'react';
import { renderHook, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const mockGet = jest.fn();

jest.mock('@/api', () => ({
  __esModule: true,
  apiClient: { get: (...args: unknown[]) => mockGet(...args) },
}));

import { useCatalogue } from '@/catalogue';

const reponse = { mode: 'asap', zone_known: false, currency: 'EUR', sectors: [] };

const enveloppe = ({ children }: { children: React.ReactNode }) => (
  <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
    {children}
  </QueryClientProvider>
);

beforeEach(() => {
  mockGet.mockReset();
  mockGet.mockResolvedValue({ data: reponse });
});

describe('useCatalogue', () => {
  it('demande le catalogue du mode choisi', async () => {
    const { result } = renderHook(() => useCatalogue('asap'), { wrapper: enveloppe });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockGet).toHaveBeenCalledWith('/client/catalogue', { params: { mode: 'asap' } });
    expect(result.current.data).toEqual(reponse);
  });

  it('témoin : le rendez-vous envoie son propre mode', async () => {
    const { result } = renderHook(() => useCatalogue('scheduled'), { wrapper: enveloppe });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockGet).toHaveBeenCalledWith('/client/catalogue', { params: { mode: 'scheduled' } });
  });
});
