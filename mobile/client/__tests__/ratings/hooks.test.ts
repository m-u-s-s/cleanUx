import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';

jest.mock('@/storage/secureStore');

describe('Ratings API', () => {
  let mock: MockAdapter;
  beforeEach(() => { mock = new MockAdapter(apiClient); });
  afterEach(() => mock.restore());

  it('submits rating', async () => {
    mock.onPost('/client/bookings/1/rating').reply(200);
    const res = await apiClient.post('/client/bookings/1/rating', { overall: 5 });
    expect(res.status).toBe(200);
  });
});
