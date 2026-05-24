import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';
jest.mock('@/storage/secureStore');

describe('Presence API', () => {
  let mock: MockAdapter;
  beforeEach(() => {
    mock = new MockAdapter(apiClient);
  });
  afterEach(() => mock.restore());

  it('sends heartbeat', async () => {
    mock.onPost('/provider/presence-v2/heartbeat').reply(200);
    const res = await apiClient.post('/provider/presence-v2/heartbeat', { status: 'online' });
    expect(res.status).toBe(200);
  });

  it('goes online', async () => {
    mock.onPost('/provider/presence/online').reply(200);
    const res = await apiClient.post('/provider/presence/online');
    expect(res.status).toBe(200);
  });
});
