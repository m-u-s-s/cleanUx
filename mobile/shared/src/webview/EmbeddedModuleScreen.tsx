import React, { useCallback, useEffect, useRef, useState } from 'react';
import { View, ActivityIndicator } from 'react-native';
import { WebView } from 'react-native-webview';
import { fetchWebViewUrl } from './useWebViewTicket';
import { parseBridgeMessage, INJECTED_BRIDGE_JS } from './bridge';
import { ErrorState } from '@/ui';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';

export interface EmbeddedModuleScreenProps {
  /** Internal web path to render (e.g. '/admin/audit'). */
  path: string;
  /** Display title. Consumed by the route wrapper's native header, not rendered here. */
  title: string;
  /** Stable device identifier for ticket binding. */
  deviceId: string;
  /** Called when an embedded page hands off to a native route. */
  onOpenNative?: (route: string) => void;
  /** Called when the bridge requests closing the embedded view. */
  onRequestBack?: () => void;
}

type Status = 'loading' | 'ready' | 'error';

/** Max automatic silent re-handoffs before giving up (spec: one retry, then stop). */
const MAX_SESSION_RETRIES = 1;

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
  const theme = useThemeColors();
  const [url, setUrl] = useState<string | null>(null);
  const [status, setStatus] = useState<Status>('loading');

  // Survives renders without retriggering them.
  const mountedRef = useRef(true);
  const sessionRetriesRef = useRef(0);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const load = useCallback(async () => {
    setStatus('loading');
    try {
      const resolved = await fetchWebViewUrl(path, deviceId);
      if (mountedRef.current) {
        setUrl(resolved);
        setStatus('ready');
      }
    } catch {
      if (mountedRef.current) {
        setStatus('error');
      }
    }
  }, [path, deviceId]);

  // Manual retry (error-state button) resets the automatic-retry budget.
  const manualRetry = useCallback(() => {
    sessionRetriesRef.current = 0;
    load();
  }, [load]);

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
          // One silent re-handoff using the still-valid Sanctum token; if the
          // page expires the session again, stop looping and surface an error.
          if (sessionRetriesRef.current < MAX_SESSION_RETRIES) {
            sessionRetriesRef.current += 1;
            load();
          } else {
            setStatus('error');
          }
          break;
        case 'error':
          setStatus('error');
          break;
        case 'ready':
        default:
          // 'ready' is informational; the ready UI state is driven by load().
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
          onRetry={manualRetry}
        />
      </View>
    );
  }

  if (status === 'loading' || !url) {
    return (
      <View
        testID="embedded-loading"
        style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: theme.bg }}
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
