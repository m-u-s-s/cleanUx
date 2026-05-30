import { apiClient } from '@/api';

/**
 * Requests a single-use handoff URL for an authenticated WebView session at
 * the given internal web path. The returned URL is loaded directly by the
 * WebView; it logs the user into a web session and redirects to <path>?embed=1.
 */
export async function fetchWebViewUrl(targetPath: string, deviceId: string): Promise<string> {
  const res = await apiClient.post('/auth/webview-ticket', {
    target_path: targetPath,
    device_id: deviceId,
  });
  return res.data.url as string;
}
