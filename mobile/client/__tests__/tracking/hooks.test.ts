import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';

jest.mock('@/storage/secureStore');

describe('Tracking API', () => {
  let mock: MockAdapter;
  beforeEach(() => {
    mock = new MockAdapter(apiClient);
  });
  afterEach(() => mock.restore());

  it('fetches tracking session', async () => {
    mock.onGet('/client/bookings/1/tracking').reply(200, {
      id: 10,
      booking_id: 1,
      status: 'enroute',
      points: [],
    });
    const res = await apiClient.get('/client/bookings/1/tracking');
    expect(res.data.status).toBe('enroute');
    expect(res.data.booking_id).toBe(1);
  });

  it('fetches tracking trail', async () => {
    mock.onGet('/client/bookings/1/tracking/trail').reply(200, {
      data: [{ latitude: 48.85, longitude: 2.35, recorded_at: '2026-05-24T10:00:00Z' }],
    });
    const res = await apiClient.get('/client/bookings/1/tracking/trail');
    expect(res.data.data[0].latitude).toBe(48.85);
  });

  it('fetches ETA info', async () => {
    mock.onGet('/client/bookings/1/eta').reply(200, {
      eta_minutes: 12,
      distance_km: 3.4,
    });
    const res = await apiClient.get('/client/bookings/1/eta');
    expect(res.data.eta_minutes).toBe(12);
    expect(res.data.distance_km).toBe(3.4);
  });

  it('handles empty trail gracefully', async () => {
    mock.onGet('/client/bookings/2/tracking/trail').reply(200, { data: [] });
    const res = await apiClient.get('/client/bookings/2/tracking/trail');
    expect(Array.isArray(res.data.data)).toBe(true);
    expect(res.data.data.length).toBe(0);
  });

  it('returns session with eta_minutes and distance_km', async () => {
    mock.onGet('/client/bookings/5/tracking').reply(200, {
      id: 50,
      booking_id: 5,
      status: 'in_mission',
      eta_minutes: 0,
      distance_km: 0,
      points: [],
    });
    const res = await apiClient.get('/client/bookings/5/tracking');
    expect(res.data.status).toBe('in_mission');
    expect(res.data.eta_minutes).toBe(0);
  });
});
