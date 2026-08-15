import React from 'react';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { NotificationDetailView } from '@/notifications';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'ProviderNotificationDetail'>;

/**
 * La fiche est écrite une fois dans `@/notifications` ; cet écran ne fait que la brancher sur la
 * pile du prestataire. Le lien de résolution vise une page web de l'application : il passe par
 * l'hôte WebView, qui porte la session — ouvrir le navigateur enverrait l'utilisateur sur un
 * écran de connexion.
 */
export function ProviderNotificationDetailScreen({ route, navigation }: Props) {
  return (
    <NotificationDetailView
      id={route.params.id}
      onOpenPath={(path, title) => navigation.navigate('EmbeddedModule', { path, title })}
    />
  );
}
