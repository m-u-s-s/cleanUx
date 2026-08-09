import { useEffect } from 'react';
import { apiClient, getAppAudience } from '@/api';
import { useAuth } from '@/auth';
import { Platform } from 'react-native';
import { isPushModuleAvailable } from './availability';

/**
 * ENREGISTRER LE TÉLÉPHONE — sans quoi le serveur n'a personne à qui envoyer.
 *
 * `require` ET NON `await import` : Jest refuse l'import dynamique sans
 * `--experimental-vm-modules`, si bien que le `catch` silencieux avalait l'erreur et rendait
 * l'enregistrement INVÉRIFIABLE — vert pour la pire des raisons. La garde `isPushModuleAvailable()`
 * juste au-dessus est ce qui protège du vrai danger (le module qui explose à l'import sous Expo Go),
 * pas la forme de l'import.
 */
export function useRegisterPushToken() {
  const { isAuthenticated } = useAuth();

  useEffect(() => {
    if (!isAuthenticated) return;
    // Android/Expo Go (SDK 53+): the import itself throws — see ./availability.
    if (!isPushModuleAvailable()) return;

    (async () => {
      try {
        // eslint-disable-next-line @typescript-eslint/no-var-requires
        const ExpoNotifications = require('expo-notifications');
        const { status } = await ExpoNotifications.requestPermissionsAsync();
        if (status !== 'granted') return;

        const token = await ExpoNotifications.getExpoPushTokenAsync();
        if (!token?.data) return;

        /*
         * L'ESPACE DE L'APPLICATION QUI PARLE, pas « client » en dur.
         *
         * Les deux applications partagent ce hook. Enregistrer l'appareil d'un prestataire sur
         * `/client/devices/register` fonctionnait — les deux routes sont ouvertes à tout compte
         * authentifié — mais rangeait son téléphone du mauvais côté : les jetons d'une flotte
         * entière apparaissaient comme des appareils clients, et un filtre par espace ne les
         * retrouvait plus. `client` reste le défaut : une application qui ne se déclare pas est
         * l'application cliente déjà installée sur le parc.
         */
        const espace = getAppAudience() === 'provider' ? 'provider' : 'client';

        await apiClient.post(`/${espace}/devices/register`, {
          token: token.data,
          platform: Platform.OS,
          provider: 'expo',
        });
      } catch {
        // Permission refusée, module absent, réseau coupé : aucun de ces cas ne doit interrompre
        // l'application. L'utilisateur ne recevra pas de notification, et le dira au support —
        // c'est préférable à un écran blanc au démarrage.
      }
    })();
  }, [isAuthenticated]);
}
