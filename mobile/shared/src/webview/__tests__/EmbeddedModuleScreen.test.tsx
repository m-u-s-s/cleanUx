import React from 'react';
import { render, waitFor } from '@testing-library/react-native';
import { EmbeddedModuleScreen } from '../EmbeddedModuleScreen';
import * as ticket from '../useWebViewTicket';

jest.mock('../useWebViewTicket');

describe('EmbeddedModuleScreen', () => {
  beforeEach(() => jest.clearAllMocks());

  it('fetches a handoff url for the given path and loads it in the WebView', async () => {
    (ticket.fetchWebViewUrl as jest.Mock).mockResolvedValue('https://app/m/enter?ticket=t');

    const { getByTestId } = render(
      <EmbeddedModuleScreen path="/admin/audit" title="Audit" deviceId="dev-1" />,
    );

    await waitFor(() => {
      expect(ticket.fetchWebViewUrl).toHaveBeenCalledWith('/admin/audit', 'dev-1');
      expect(getByTestId('mock-webview').props.accessibilityLabel).toBe('https://app/m/enter?ticket=t');
    });
  });

  it('shows an error state when the handoff fails', async () => {
    (ticket.fetchWebViewUrl as jest.Mock).mockRejectedValue(new Error('offline'));

    const { getByTestId } = render(
      <EmbeddedModuleScreen path="/admin/audit" title="Audit" deviceId="dev-1" />,
    );

    await waitFor(() => expect(getByTestId('embedded-error')).toBeTruthy());
  });

  it('calls onOpenNative when the page posts an openNative bridge message', async () => {
    (ticket.fetchWebViewUrl as jest.Mock).mockResolvedValue('https://app/m/enter?ticket=t');
    const onOpenNative = jest.fn();

    const { getByTestId } = render(
      <EmbeddedModuleScreen path="/x" title="X" deviceId="d" onOpenNative={onOpenNative} />,
    );

    await waitFor(() => getByTestId('mock-webview'));
    const webview = getByTestId('mock-webview');
    webview.props.onMessage({ nativeEvent: { data: JSON.stringify({ type: 'openNative', route: '/booking/new' }) } });

    expect(onOpenNative).toHaveBeenCalledWith('/booking/new');
  });
});
