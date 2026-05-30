import { fetchWebViewUrl } from '../useWebViewTicket';
import { apiClient } from '@/api';

jest.mock('@/api', () => ({
  apiClient: { post: jest.fn() },
  ApiError: class ApiError extends Error {},
}));

describe('fetchWebViewUrl', () => {
  it('posts target_path + device_id and returns the handoff url', async () => {
    (apiClient.post as jest.Mock).mockResolvedValue({ data: { ok: true, url: 'https://app/m/enter?ticket=abc' } });

    const url = await fetchWebViewUrl('/admin/audit', 'device-9');

    expect(apiClient.post).toHaveBeenCalledWith('/auth/webview-ticket', {
      target_path: '/admin/audit',
      device_id: 'device-9',
    });
    expect(url).toBe('https://app/m/enter?ticket=abc');
  });
});
