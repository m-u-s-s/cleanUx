import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';
import { apiClient, ApiError } from '@/api/index';
import { secureStore } from '@/storage/secureStore';

jest.mock('@/storage/secureStore');
const mockSecureStore = secureStore as jest.Mocked<typeof secureStore>;

// We need to spy on bare axios.post for the refresh call (it uses plain axios, not apiClient)
const axiosPostSpy = jest.spyOn(axios, 'post');

let mock: MockAdapter;

beforeEach(() => {
  jest.clearAllMocks();
  mock = new MockAdapter(apiClient);
  axiosPostSpy.mockReset();
});

afterEach(() => {
  mock.restore();
});

describe('API client', () => {
  it('attaches Bearer token from secureStore', async () => {
    mockSecureStore.getToken.mockResolvedValue('test-token');
    mock.onGet('/auth/me').reply(200, { id: 1, name: 'Alice' });

    const response = await apiClient.get('/auth/me');

    expect(response.status).toBe(200);
    expect(response.config.headers?.['Authorization']).toBe('Bearer test-token');
  });

  it('skips Authorization when no token', async () => {
    mockSecureStore.getToken.mockResolvedValue(null);
    mock.onGet('/auth/me').reply(200, { id: 1, name: 'Alice' });

    const response = await apiClient.get('/auth/me');

    expect(response.status).toBe(200);
    expect(response.config.headers?.['Authorization']).toBeUndefined();
  });

  it('wraps API errors into ApiError with error_code', async () => {
    mockSecureStore.getToken.mockResolvedValue(null);
    mock.onPost('/auth/login').reply(422, {
      ok: false,
      error_code: 'validation_failed',
      message: 'The given data was invalid.',
      errors: { email: ['The email field is required.'] },
    });

    let caught: unknown;
    try {
      await apiClient.post('/auth/login', { email: '', password: '' });
    } catch (err) {
      caught = err;
    }

    expect(caught).toBeInstanceOf(ApiError);
    const apiErr = caught as ApiError;
    expect(apiErr.status).toBe(422);
    expect(apiErr.errorCode).toBe('validation_failed');
    expect(apiErr.message).toBe('The given data was invalid.');
    expect(apiErr.errors).toEqual({ email: ['The email field is required.'] });
  });

  it('retries request after 401 by refreshing token', async () => {
    mockSecureStore.getToken.mockResolvedValue('old-token');
    mockSecureStore.setToken.mockResolvedValue(undefined);

    // First call to /auth/me returns 401
    // Second call (after refresh) returns 200
    mock
      .onGet('/auth/me')
      .replyOnce(401, { ok: false, error_code: 'token_expired', message: 'Unauthenticated.' })
      .onGet('/auth/me')
      .replyOnce(200, { id: 1, name: 'Alice' });

    // Refresh call returns new token
    axiosPostSpy.mockResolvedValueOnce({ data: { token: 'new-token' } });

    const response = await apiClient.get('/auth/me');

    expect(axiosPostSpy).toHaveBeenCalledTimes(1);
    expect(mockSecureStore.setToken).toHaveBeenCalledWith('new-token');
    expect(response.status).toBe(200);
  });

  it('clears token on refresh failure', async () => {
    mockSecureStore.getToken.mockResolvedValue('old-token');
    mockSecureStore.clearToken.mockResolvedValue(undefined);

    mock
      .onGet('/auth/me')
      .replyOnce(401, { ok: false, error_code: 'token_expired', message: 'Unauthenticated.' });

    // Refresh itself fails with 401
    axiosPostSpy.mockRejectedValueOnce(
      Object.assign(new Error('Unauthorized'), {
        response: {
          status: 401,
          data: { ok: false, error_code: 'refresh_failed', message: 'Invalid refresh token.' },
        },
      }),
    );

    let caught: unknown;
    try {
      await apiClient.get('/auth/me');
    } catch (err) {
      caught = err;
    }

    expect(mockSecureStore.clearToken).toHaveBeenCalledTimes(1);
    expect(caught).toBeInstanceOf(ApiError);
    const apiErr = caught as ApiError;
    expect(apiErr.status).toBe(401);
    expect(apiErr.errorCode).toBe('session_expired');
  });
});
