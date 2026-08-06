import React from 'react';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { EmbeddedModuleScreen, useDeviceId } from '@/webview';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'EmbeddedModule'>;

/**
 * L'ESPACE SOCIÉTÉ, SERVI PAR L'HÔTE WEBVIEW PARTAGÉ.
 *
 * L'application cliente exposait déjà cette route ; la prestataire, non — alors que `@/webview`
 * est bien aliasé dans ses TROIS tables (tsconfig, Babel, Jest, vérifiées une à une : elles
 * décrivent les mêmes chemins sans jamais se contrôler mutuellement).
 *
 * Conséquence : répartition, équipes terrain, canaux et gestion des membres n'étaient atteignables
 * que depuis un navigateur. Les embarquer ici les rend disponibles tout de suite, et laisse la
 * migration vers du natif se faire écran par écran — c'est l'intérêt de l'approche hybride.
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
        // Tant qu'aucune correspondance chemin → écran natif n'existe, on ré-embarque la cible
        // plutôt que de laisser le lien sans effet.
        navigation.push('EmbeddedModule', { path: target, title });
      }}
    />
  );
}
