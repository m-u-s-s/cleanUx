import React from 'react';
import { renderHook, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useLogin } from '@/auth/useLogin';
import { apiClient } from '@/api';
import { secureStore } from '@/storage/secureStore';
import MockAdapter from 'axios-mock-adapter';

jest.mock('@/storage/secureStore');
const mockStore = secureStore as jest.Mocked<typeof secureStore>;

const wrapper = ({ children }: { children: React.ReactNode }) => (
  <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { retry: false } } })}>
    {children}
  </QueryClientProvider>
);

describe('useLogin', () => {
  let mock: MockAdapter;
  beforeEach(() => { mock = new MockAdapter(apiClient); jest.clearAllMocks(); });
  afterEach(() => mock.restore());

  it('stores token and returns user on success', async () => {
    mock.onPost('/auth/login').reply(200, {
      ok: true, token: 'tok_123', user: { id: 1, name: 'Test', email: 'a@b.c', role: 'client' },
    });

    const { result } = renderHook(() => useLogin(), { wrapper });
    result.current.mutate({ email: 'a@b.c', password: '12345678' });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.token).toBe('tok_123');
    expect(result.current.data?.user.name).toBe('Test');
    expect(mockStore.setToken).toHaveBeenCalledWith('tok_123');
  });
});
