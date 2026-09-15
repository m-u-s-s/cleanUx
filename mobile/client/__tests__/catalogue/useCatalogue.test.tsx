import React from 'react';
import { act, renderHook, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const mockGet = jest.fn();

jest.mock('@/api', () => ({
  __esModule: true,
  apiClient: { get: (...args: unknown[]) => mockGet(...args) },
}));

import { useCatalogue } from '@/catalogue';
import { adopterLaLangueDuCompte } from '@/i18n/langue';

const reponse = { mode: 'asap', zone_known: false, currency: 'EUR', sectors: [] };

const nouveauClient = () => new QueryClient({ defaultOptions: { queries: { retry: false } } });

const enveloppePour = (client: QueryClient) =>
  ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  );

const enveloppe = ({ children }: { children: React.ReactNode }) => enveloppePour(nouveauClient())({ children });

beforeEach(() => {
  mockGet.mockReset();
  mockGet.mockResolvedValue({ data: reponse });
});

afterEach(() => {
  act(() => adopterLaLangueDuCompte('fr'));
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

  it('changer de langue redemande le catalogue, rangé sous la nouvelle langue', async () => {
    // Les libellés sont traduits par le serveur : une réponse en français gardée en cache
    // resterait affichée à un client passé au néerlandais.
    const client = nouveauClient();
    const { result } = renderHook(() => useCatalogue('asap'), { wrapper: enveloppePour(client) });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockGet).toHaveBeenCalledTimes(1);
    expect(client.getQueryCache().find({ queryKey: ['catalogue', 'asap', 'fr'], exact: true })).toBeDefined();

    act(() => adopterLaLangueDuCompte('nl'));

    await waitFor(() => expect(mockGet).toHaveBeenCalledTimes(2));
    expect(client.getQueryCache().find({ queryKey: ['catalogue', 'asap', 'nl'], exact: true })).toBeDefined();
  });
});
