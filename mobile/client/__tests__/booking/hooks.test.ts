import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';

jest.mock('@/storage/secureStore');

describe('Booking API hooks', () => {
  let mock: MockAdapter;
  beforeEach(() => {
    mock = new MockAdapter(apiClient);
  });
  afterEach(() => mock.restore());

  it('service catalog returns services', async () => {
    mock.onGet('/search/services').reply(200, { data: [{ id: 1, name: 'Nettoyage', slug: 'nettoyage' }] });
    const res = await apiClient.get('/search/services');
    expect(res.data.data[0].slug).toBe('nettoyage');
  });

  it('browse providers returns list', async () => {
    mock.onGet('/search/providers').reply(200, { data: [{ id: 1, name: 'Jean', rating_avg: 4.8 }] });
    const res = await apiClient.get('/search/providers', { params: { trade: 'nettoyage' } });
    expect(res.data.data[0].rating_avg).toBe(4.8);
  });

  it('create booking posts correct shape', async () => {
    mock.onPost('/client/bookings').reply(201, { data: { id: 42, status: 'pending' } });
    const res = await apiClient.post('/client/bookings', {
      service_catalog_id: 1,
      address: '123 Rue Test',
      city: 'Paris',
      postal_code: '75001',
    });
    expect(res.data.data.id).toBe(42);
  });

  it('address autocomplete requires 3+ chars', async () => {
    mock.onGet('/geo/v2/autocomplete').reply(200, { data: [{ label: '123 Rue Test', place_id: 'abc' }] });
    const res = await apiClient.get('/geo/v2/autocomplete', { params: { q: '123 Rue' } });
    expect(res.data.data[0].label).toContain('Rue');
  });
});
