// Expo embeds public variables only when their names are statically referenced.
export const env = {
  apiUrl: process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000/api',
  webUrl: process.env.EXPO_PUBLIC_WEB_URL ?? 'http://localhost:8000',
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
