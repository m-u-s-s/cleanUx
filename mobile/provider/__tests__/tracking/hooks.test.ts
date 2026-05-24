import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';
jest.mock('@/storage/secureStore');

describe('Provider tracking API', () => {
  let mock: MockAdapter;
  beforeEach(() => { mock = new MockAdapter(apiClient); });
  afterEach(() => mock.restore());

  it('starts tracking session', async () => {
    mock.onPost('/provider/bookings/1/tracking/start').reply(200, { id: 10, status: 'enroute' });
    const res = await apiClient.post('/provider/bookings/1/tracking/start');
    expect(res.data.status).toBe('enroute');
  });

  it('pushes live position', async () => {
    mock.onPost('/provider/missions/1/live/position').reply(200);
    expect(
      (await apiClient.post('/provider/missions/1/live/position', { latitude: 48.85, longitude: 2.35 })).status,
    ).toBe(200);
  });
});
