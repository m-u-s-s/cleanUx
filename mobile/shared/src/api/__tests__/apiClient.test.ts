/**
 * Tests for the shared apiClient (axios instance + interceptors).
 * Runs from mobile/client via jest-expo preset with module-name-mapper.
 */
import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

// jest.mock must be declared before the module under test is imported
jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(),
  setItemAsync: jest.fn(),
  deleteItemAsync: jest.fn(),
}));

import * as ExpoSecureStore from 'expo-secure-store';
import { apiClient, ApiError } from '../index';

const mock = new MockAdapter(apiClient);

const getItemAsync = ExpoSecureStore.getItemAsync as jest.Mock;
const setItemAsync = ExpoSecureStore.setItemAsync as jest.Mock;
const deleteItemAsync = ExpoSecureStore.deleteItemAsync as jest.Mock;

beforeEach(() => {
  mock.reset();
  jest.clearAllMocks();
  getItemAsync.mockResolvedValue(null);
  setItemAsync.mockResolvedValue(undefined);
  deleteItemAsync.mockResolvedValue(undefined);
});

describe('apiClient configuration', () => {
  it('is created with correct baseURL from env', () => {
    expect(apiClient.defaults.baseURL).toBe(
      process.env['EXPO_PUBLIC_API_URL'] ?? 'http://localhost:8000/api',
    );
  });

  it('sets Accept and Content-Type headers by default', () => {
    expect(apiClient.defaults.headers['Content-Type']).toBe('application/json');
    expect(apiClient.defaults.headers['Accept']).toBe('application/json');
  });
});

describe('request interceptor', () => {
  it('attaches Bearer token when one is stored', async () => {
    getItemAsync.mockResolvedValue('my-token');
    mock.onGet('/ping').reply(200, { ok: true });

    const res = await apiClient.get('/ping');

    expect(res.config.headers?.['Authorization']).toBe('Bearer my-token');
  });

  it('does not set Authorization header when no token is stored', async () => {
    getItemAsync.mockResolvedValue(null);
    mock.onGet('/ping').reply(200, { ok: true });

    const res = await apiClient.get('/ping');

    expect(res.config.headers?.['Authorization']).toBeUndefined();
  });
});

describe('response interceptor — 401 handling', () => {
  it('clears the token and throws session_expired when refresh also fails', async () => {
    getItemAsync.mockResolvedValue('stale-token');

    // First call returns 401 (no error_code), second refresh also fails
    mock.onGet('/protected').replyOnce(401, { ok: false, message: 'Unauthorized' });
    // The interceptor tries POST /auth/refresh via plain axios — mock at network level
    const axiosMock = new MockAdapter(axios);
    axiosMock.onPost(/auth\/refresh/).networkError();

    await expect(apiClient.get('/protected')).rejects.toMatchObject({
      name: 'ApiError',
      errorCode: 'session_expired',
      status: 401,
    });

    expect(deleteItemAsync).toHaveBeenCalled();
    axiosMock.restore();
  });

  it('does not retry when error_code is token_grace_expired', async () => {
    getItemAsync.mockResolvedValue('grace-token');
    mock.onGet('/protected').replyOnce(401, {
      ok: false,
      error_code: 'token_grace_expired',
      message: 'Grace period expired',
    });

    await expect(apiClient.get('/protected')).rejects.toMatchObject({
      name: 'ApiError',
      errorCode: 'token_grace_expired',
    });
    // Only one call — no retry
    expect(mock.history['get']?.length).toBe(1);
  });

  it('maps non-401 error responses to ApiError', async () => {
    mock.onGet('/missing').replyOnce(404, {
      ok: false,
      error_code: 'not_found',
      message: 'Resource not found',
    });

    await expect(apiClient.get('/missing')).rejects.toMatchObject({
      name: 'ApiError',
      status: 404,
      errorCode: 'not_found',
    });
  });

  it('includes field-level errors from 422 responses', async () => {
    mock.onPost('/form').replyOnce(422, {
      ok: false,
      error_code: 'validation_error',
      message: 'Validation failed',
      errors: { email: ['The email field is required.'] },
    });

    await expect(apiClient.post('/form', {})).rejects.toMatchObject({
      errors: { email: ['The email field is required.'] },
    });
  });
});
