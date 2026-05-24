import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';

jest.mock('@/storage/secureStore');

describe('Payment API', () => {
  let mock: MockAdapter;
  beforeEach(() => {
    mock = new MockAdapter(apiClient);
  });
  afterEach(() => mock.restore());

  it('fetches payment methods', async () => {
    mock.onGet('/client/payment-methods').reply(200, {
      data: [
        {
          id: 'pm_1',
          brand: 'visa',
          last4: '4242',
          exp_month: 12,
          exp_year: 2027,
          is_default: true,
        },
      ],
    });
    const res = await apiClient.get('/client/payment-methods');
    expect(res.data.data[0].last4).toBe('4242');
  });

  it('creates setup intent', async () => {
    mock
      .onPost('/client/payment-methods/setup-intent')
      .reply(200, { client_secret: 'seti_xxx_secret_yyy' });
    const res = await apiClient.post('/client/payment-methods/setup-intent');
    expect(res.data.client_secret).toContain('seti_');
  });

  it('creates payment intent for booking', async () => {
    mock
      .onPost('/client/bookings/1/payment-intent')
      .reply(200, { client_secret: 'pi_xxx_secret_yyy', amount: 5000, currency: 'eur' });
    const res = await apiClient.post('/client/bookings/1/payment-intent');
    expect(res.data.amount).toBe(5000);
  });

  it('deletes a payment method', async () => {
    mock.onDelete('/client/payment-methods/pm_1').reply(204);
    const res = await apiClient.delete('/client/payment-methods/pm_1');
    expect(res.status).toBe(204);
  });

  it('payment methods list returns empty array when no cards', async () => {
    mock.onGet('/client/payment-methods').reply(200, { data: [] });
    const res = await apiClient.get('/client/payment-methods');
    expect(res.data.data).toHaveLength(0);
  });
});
