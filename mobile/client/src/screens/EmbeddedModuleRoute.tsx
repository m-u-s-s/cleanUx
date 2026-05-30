import React from 'react';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { EmbeddedModuleScreen } from '@/webview';
import { useDeviceId } from '@/hooks/useDeviceId';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'EmbeddedModule'>;

/**
 * Connects the shared WebView host to React Navigation: titles the native
 * header, routes openNative handoffs, and maps the bridge's back request to
 * navigation.goBack().
 */
export function EmbeddedModuleRoute({ route, navigation }: Props) {
  const { path, title } = route.params;
  const deviceId = useDeviceId();

  React.useLayoutEffect(() => {
    navigation.setOptions({ title });
  }, [navigation, title]);

  return (
    <EmbeddedModuleScreen
      path={path}
      title={title}
      deviceId={deviceId}
      onRequestBack={() => navigation.goBack()}
      onOpenNative={(target) => {
        // Until a path->native map exists (sub-project 3), re-embed the target.
        navigation.push('EmbeddedModule', { path: target, title });
      }}
    />
  );
}
