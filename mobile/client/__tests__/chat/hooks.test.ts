import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';

jest.mock('@/storage/secureStore');

describe('Chat API', () => {
  let mock: MockAdapter;
  beforeEach(() => { mock = new MockAdapter(apiClient); });
  afterEach(() => mock.restore());

  it('fetches threads', async () => {
    mock.onGet('/v2/chat/threads').reply(200, {
      data: [{ id: 1, unread_count: 2, participants: [{ id: 1, name: 'Jean', role: 'client' }] }],
    });
    const res = await apiClient.get('/v2/chat/threads');
    expect(res.data.data[0].unread_count).toBe(2);
  });

  it('fetches messages for a thread', async () => {
    mock.onGet('/v2/chat/threads/1/messages').reply(200, {
      data: [{ id: 5, thread_id: 1, sender_id: 1, sender_name: 'Jean', body: 'Bonjour', created_at: '2026-01-01T10:00:00Z' }],
    });
    const res = await apiClient.get('/v2/chat/threads/1/messages');
    expect(res.data.data[0].body).toBe('Bonjour');
  });

  it('sends message', async () => {
    mock.onPost('/v2/chat/threads/1/messages').reply(201, { data: { id: 10, body: 'Hello' } });
    const res = await apiClient.post('/v2/chat/threads/1/messages', { body: 'Hello' });
    expect(res.data.data.body).toBe('Hello');
  });

  it('marks thread as read', async () => {
    mock.onPost('/v2/chat/threads/1/read').reply(200, { ok: true });
    const res = await apiClient.post('/v2/chat/threads/1/read');
    expect(res.status).toBe(200);
  });
});
