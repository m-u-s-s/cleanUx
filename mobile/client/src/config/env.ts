function optionalEnv(key: string, fallback: string = ''): string {
  return process.env[key] ?? fallback;
}

export const env = {
  apiUrl: optionalEnv('EXPO_PUBLIC_API_URL', 'http://localhost:8000/api'),
  stripePublishableKey: optionalEnv('EXPO_PUBLIC_STRIPE_PUBLISHABLE_KEY'),
  sentryDsn: optionalEnv('EXPO_PUBLIC_SENTRY_DSN'),
} as const;
