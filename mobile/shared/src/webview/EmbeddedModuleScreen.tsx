import React, { useCallback, useEffect, useState } from 'react';
import { View, ActivityIndicator } from 'react-native';
import { WebView } from 'react-native-webview';
import { fetchWebViewUrl } from './useWebViewTicket';
import { parseBridgeMessage, INJECTED_BRIDGE_JS } from './bridge';
import { ErrorState } from '@/ui';
import { colors } from '@/theme';

export interface EmbeddedModuleScreenProps {
  /** Internal web path to render (e.g. '/admin/audit'). */
  path: string;
  /** Display title (drawn by the native header in the route wrapper). */
  title: string;
  /** Stable device identifier for ticket binding. */
  deviceId: string;
  /** Called when an embedded page hands off to a native route. */
  onOpenNative?: (route: string) => void;
  /** Called when the bridge requests closing the embedded view. */
  onRequestBack?: () => void;
}

type Status = 'loading' | 'ready' | 'error';

/**
 * Renders any existing web module inside an authenticated WebView. The user is
 * already logged in (Sanctum); a single-use ticket establishes a disposable
 * web session, so embedded pages load without a second login.
 */
export function EmbeddedModuleScreen({
  path,
  deviceId,
  onOpenNative,
  onRequestBack,
}: EmbeddedModuleScreenProps) {
  const [url, setUrl] = useState<string | null>(null);
  const [status, setStatus] = useState<Status>('loading');

  const load = useCallback(async () => {
    setStatus('loading');
    try {
      setUrl(await fetchWebViewUrl(path, deviceId));
      setStatus('ready');
    } catch {
      setStatus('error');
    }
  }, [path, deviceId]);

  useEffect(() => {
    load();
  }, [load]);

  const onMessage = useCallback(
    (event: { nativeEvent: { data: string } }) => {
      const msg = parseBridgeMessage(event.nativeEvent.data);
      if (!msg) return;
      switch (msg.type) {
        case 'openNative':
          onOpenNative?.(msg.route);
          break;
        case 'requestBack':
          onRequestBack?.();
          break;
        case 'sessionExpired':
          load(); // silent re-handoff using the still-valid Sanctum token
          break;
        case 'error':
          setStatus('error');
          break;
      }
    },
    [onOpenNative, onRequestBack, load],
  );

  if (status === 'error') {
    return (
      <View testID="embedded-error" style={{ flex: 1 }}>
        <ErrorState
          message="Cette section nécessite une connexion. Réessayez."
          onRetry={load}
        />
      </View>
    );
  }

  if (status === 'loading' || !url) {
    return (
      <View
        testID="embedded-loading"
        style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.surface[50] }}
      >
        <ActivityIndicator size="large" color={colors.brand[500]} />
      </View>
    );
  }

  return (
    <WebView
      source={{ uri: url }}
      injectedJavaScript={INJECTED_BRIDGE_JS}
      onMessage={onMessage}
      onError={() => setStatus('error')}
      onHttpError={(e: any) => {
        if ((e?.nativeEvent?.statusCode ?? 0) >= 500) setStatus('error');
      }}
      startInLoadingState
    />
  );
}
