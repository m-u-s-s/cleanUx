import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';

jest.mock('@/storage/secureStore');

describe('socket config endpoint', () => {
  let mock: MockAdapter;
  beforeEach(() => { mock = new MockAdapter(apiClient); });
  afterEach(() => mock.restore());

  it('returns socket config', async () => {
    mock.onGet('/realtime/socket-config').reply(200, {
      driver: 'reverb', key: 'pk_test', host: 'ws.test', port: 443, scheme: 'https',
      auth_endpoint: '/api/broadcasting/auth',
    });
    const res = await apiClient.get('/realtime/socket-config');
    expect(res.data.key).toBe('pk_test');
    expect(res.data.port).toBe(443);
  });
});
