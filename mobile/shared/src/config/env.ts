// Expo embeds public variables only when their names are statically referenced.

/** Port de `php artisan serve`, celui que les .env d'exemple font lancer. */
const LOCAL_SERVER_PORT = 8000;

/**
 * Machine qui sert Metro en développement, lue dans `expoConfig.hostUri` (« 192.168.1.16:8081 »).
 *
 * L'API tourne sur cette même machine, et l'appareil vient de la joindre pour charger le bundle :
 * l'adresse juste est déjà connue. Une IP figée dans .env cassait la connexion à chaque changement
 * de réseau — trois adresses en une heure le 2026-09-14 — sur l'émulateur comme sur un téléphone.
 *
 * - Développement seulement : en production `__DEV__` vaut false, le module n'est pas chargé et
 *   aucune build ne peut suivre un hôte déduit.
 * - `require` paresseux : security/environment.test.cjs exécute ce fichier compilé sans aucun
 *   module disponible, et c'est voulu — la configuration de production n'en charge aucun.
 * - Seuls un nom d'hôte ou une IPv4 sont retenus ; le reste retombe sur localhost plutôt que
 *   d'être glissé dans une URL.
 */
function devServerHost(): string | null {
  if (typeof __DEV__ === 'undefined' || !__DEV__) {
    return null;
  }

  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const Constants = require('expo-constants').default as { expoConfig?: { hostUri?: unknown } | null };
    const hostUri = Constants.expoConfig?.hostUri;
    const match = typeof hostUri === 'string' ? /^([A-Za-z0-9.-]+)(?::\d+)?(?:\/.*)?$/.exec(hostUri) : null;

    return match?.[1] ?? null;
  } catch {
    return null;
  }
}

const localOrigin = `http://${devServerHost() ?? 'localhost'}:${LOCAL_SERVER_PORT}`;

export const env = {
  // `||` et non `??` : une ligne `EXPO_PUBLIC_API_URL=` laissée vide dans .env n'est pas une
  // adresse. Elle doit céder la place à l'hôte déduit plutôt que de vider la baseURL.
  apiUrl: process.env.EXPO_PUBLIC_API_URL || `${localOrigin}/api`,
  webUrl: process.env.EXPO_PUBLIC_WEB_URL || localOrigin,
  stripePublishableKey: process.env.EXPO_PUBLIC_STRIPE_PUBLISHABLE_KEY ?? '',
  sentryDsn: process.env.EXPO_PUBLIC_SENTRY_DSN ?? '',
  /**
   * Clé publique Cloudflare Turnstile. Vide en dev : le widget est alors sauté et le serveur
   * laisse passer l'inscription (VerifyTurnstileCaptcha ne bloque hors production que si une
   * clé secrète est configurée). En production, elle DOIT être renseignée, sinon l'inscription
   * est refusée côté serveur.
   */
  turnstileSiteKey: process.env.EXPO_PUBLIC_TURNSTILE_SITE_KEY ?? '',
} as const;
