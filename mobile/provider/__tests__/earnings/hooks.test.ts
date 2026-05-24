import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';
jest.mock('@/storage/secureStore');

describe('Earnings API', () => {
  let mock: MockAdapter;
  beforeEach(() => { mock = new MockAdapter(apiClient); });
  afterEach(() => mock.restore());

  it('fetches wallet balance', async () => {
    mock.onGet('/provider/wallet/balance').reply(200, { available: 150.50, pending: 30, currency: 'EUR' });
    const res = await apiClient.get('/provider/wallet/balance');
    expect(res.data.available).toBe(150.50);
  });

  it('fetches stripe connect status', async () => {
    mock
      .onGet('/provider/stripe-connect/status')
      .reply(200, { onboarded: true, charges_enabled: true, payouts_enabled: true, requirements: [] });
    const res = await apiClient.get('/provider/stripe-connect/status');
    expect(res.data.onboarded).toBe(true);
  });
});
