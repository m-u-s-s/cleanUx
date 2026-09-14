import { useEffect } from 'react';
import { useNavigation } from '@react-navigation/native';
import { isPushModuleAvailable } from './availability';

/** La charge utile d'une notification, telle que le serveur l'écrit. */
export type ChargeUtilePush = Record<string, unknown>;

/** Où mène une notification, ou `null` quand elle ne mène nulle part. */
export type DestinationPush = { ecran: string; params?: Record<string, unknown> } | null;

export type RouteurDePush = (data: ChargeUtilePush) => DestinationPush;

/**
 * DEUX CONTRATS ÉCRITS SÉPARÉMENT, JAMAIS CONFRONTÉS.
 *
 * Ce hook n'agissait que sur `data.screen`. Or `grep "'screen' =>" app/` rend ZÉRO : les 84
 * charges utiles du serveur envoient `type` et des identifiants. Résultat mesurable à l'usage :
 * « Votre prestataire arrive » ouvrait l'application là où elle était, jamais sur le suivi.
 *
 * Le nom d'écran ne peut pas vivre ici — `shared` ne connaît ni la pile du client ni celle du
 * prestataire. Chaque application passe donc SA table ; `data.screen` reste honoré quand le
 * serveur le fournit, pour ne rien casser le jour où il s'y mettrait.
 */
export function useNotificationRouting(routeur?: RouteurDePush): void {
  // Use the any-typed navigation to avoid coupling shared to a specific param list
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const navigation = useNavigation<any>();

  useEffect(() => {
    // Android/Expo Go (SDK 53+): the import itself throws — see ./availability.
    if (!isPushModuleAvailable()) return;

    let sub: { remove: () => void } | null = null;

    (async () => {
      try {
        const Notifications = await import('expo-notifications');
        sub = Notifications.addNotificationResponseReceivedListener(response => {
          const data = (response.notification.request.content.data ?? {}) as ChargeUtilePush;
          const destination = destinationDe(data, routeur);

          if (destination) {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            navigation.navigate(destination.ecran, (destination.params ?? {}) as any);
          }
        });
      } catch {
        // expo-notifications not available in Expo Go SDK 53+
      }
    })();

    return () => { sub?.remove(); };
  }, [navigation, routeur]);
}

/**
 * Exportée pour être exercée sans monter de navigateur : le corps du listener, lui, ne s'exécute
 * jamais sous Jest — `expo-notifications` manque, l'import échoue, le `catch` est muet.
 */
export function destinationDe(data: ChargeUtilePush, routeur?: RouteurDePush): DestinationPush {
  // `screen` d'abord : c'est le contrat explicite, il doit gagner sur toute déduction.
  if (typeof data.screen === 'string' && data.screen !== '') {
    return {
      ecran: data.screen,
      params: (data.params ?? {}) as Record<string, unknown>,
    };
  }

  return routeur ? routeur(data) : null;
}
