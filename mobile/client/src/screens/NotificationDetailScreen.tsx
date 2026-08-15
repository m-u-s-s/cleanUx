import React from 'react';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { NotificationDetailView } from '@/notifications';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'NotificationDetail'>;

/**
 * Jumeau de `ProviderNotificationDetailScreen` — mais un jumeau de DIX lignes : tout le rendu vit
 * dans `@/notifications`. C'est le copier-coller intégral des deux écrans de liste qui avait
 * laissé le même défaut d'affichage exister deux fois.
 */
export function NotificationDetailScreen({ route, navigation }: Props) {
  return (
    <NotificationDetailView
      id={route.params.id}
      onOpenPath={(path, title) => navigation.navigate('EmbeddedModule', { path, title })}
    />
  );
}
