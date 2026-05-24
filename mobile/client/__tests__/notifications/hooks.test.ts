import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';

jest.mock('@/storage/secureStore');

describe('Notifications API', () => {
  let mock: MockAdapter;
  beforeEach(() => { mock = new MockAdapter(apiClient); });
  afterEach(() => mock.restore());

  it('fetches notifications', async () => {
    mock.onGet('/notifications').reply(200, {
      data: [{ id: '1', type: 'booking', title: 'Test', body: 'Body', read_at: null, created_at: '2026-01-01T10:00:00Z' }],
    });
    const res = await apiClient.get('/notifications');
    expect(res.data.data[0].title).toBe('Test');
  });

  it('marks all notifications as read', async () => {
    mock.onPost('/notifications/read-all').reply(200, { ok: true });
    const res = await apiClient.post('/notifications/read-all');
    expect(res.status).toBe(200);
  });

  it('handles empty notifications list', async () => {
    mock.onGet('/notifications').reply(200, { data: [] });
    const res = await apiClient.get('/notifications');
    expect(res.data.data).toHaveLength(0);
  });
});
