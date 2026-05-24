import * as Sentry from '@sentry/react-native';
import { env } from '@/config/env';

const DSN = env.sentryDsn;

if (DSN) {
  Sentry.init({
    dsn: DSN,
    tracesSampleRate: __DEV__ ? 1.0 : 0.2,
    debug: __DEV__,
    enabled: !__DEV__,
  });
}

export { Sentry };
