import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';
jest.mock('@/storage/secureStore');

describe('Mission API', () => {
  let mock: MockAdapter;
  beforeEach(() => {
    mock = new MockAdapter(apiClient);
  });
  afterEach(() => mock.restore());

  it('fetches mission inbox', async () => {
    mock
      .onGet('/provider/assignments/inbox')
      .reply(200, { data: [{ id: 1, service_name: 'Nettoyage' }] });
    const res = await apiClient.get('/provider/assignments/inbox');
    expect(res.data.data[0].service_name).toBe('Nettoyage');
  });

  it('accepts assignment', async () => {
    mock.onPost('/provider/assignments/1/accept').reply(200);
    expect((await apiClient.post('/provider/assignments/1/accept')).status).toBe(200);
  });

  it('declines assignment', async () => {
    mock.onPost('/provider/assignments/1/decline').reply(200);
    expect((await apiClient.post('/provider/assignments/1/decline')).status).toBe(200);
  });

  it('starts mission lifecycle', async () => {
    mock.onPost('/provider/missions/1/start').reply(200);
    expect((await apiClient.post('/provider/missions/1/start')).status).toBe(200);
  });
});
