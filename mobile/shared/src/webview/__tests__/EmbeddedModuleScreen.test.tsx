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

  it('retries once on sessionExpired then falls to error state (no unbounded loop)', async () => {
    (ticket.fetchWebViewUrl as jest.Mock).mockResolvedValue('https://app/m/enter?ticket=t');

    const { getByTestId } = render(
      <EmbeddedModuleScreen path="/x" title="X" deviceId="d" />,
    );

    await waitFor(() => getByTestId('mock-webview'));
    expect(ticket.fetchWebViewUrl).toHaveBeenCalledTimes(1);

    // First sessionExpired → exactly one silent retry (re-fetch).
    getByTestId('mock-webview').props.onMessage({
      nativeEvent: { data: JSON.stringify({ type: 'sessionExpired' }) },
    });
    await waitFor(() => expect(ticket.fetchWebViewUrl).toHaveBeenCalledTimes(2));
    await waitFor(() => getByTestId('mock-webview'));

    // Second sessionExpired → NO further retry; error state instead.
    getByTestId('mock-webview').props.onMessage({
      nativeEvent: { data: JSON.stringify({ type: 'sessionExpired' }) },
    });
    await waitFor(() => expect(getByTestId('embedded-error')).toBeTruthy());
    expect(ticket.fetchWebViewUrl).toHaveBeenCalledTimes(2); // not a third time
  });

  it('calls onRequestBack when the page posts a requestBack bridge message', async () => {
    (ticket.fetchWebViewUrl as jest.Mock).mockResolvedValue('https://app/m/enter?ticket=t');
    const onRequestBack = jest.fn();

    const { getByTestId } = render(
      <EmbeddedModuleScreen path="/x" title="X" deviceId="d" onRequestBack={onRequestBack} />,
    );

    await waitFor(() => getByTestId('mock-webview'));
    getByTestId('mock-webview').props.onMessage({
      nativeEvent: { data: JSON.stringify({ type: 'requestBack' }) },
    });

    expect(onRequestBack).toHaveBeenCalled();
  });
});
