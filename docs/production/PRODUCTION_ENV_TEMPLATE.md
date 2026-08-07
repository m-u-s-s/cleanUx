# Brio — Production Environment Variables

Copy to `.env` on your production server and fill in all values marked `CHANGE_ME`.
Never commit the actual `.env` to version control.

## App

```env
APP_NAME=Brio
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.brio.com
APP_KEY=                          # php artisan key:generate
APP_TIMEZONE=Europe/Brussels
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

## Database

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=brio
DB_USERNAME=brio
DB_PASSWORD=CHANGE_ME
```

## Cache / Queue / Session

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Mail

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=CHANGE_ME
MAIL_PASSWORD=CHANGE_ME
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@brio.com
MAIL_FROM_NAME=Brio
```

## Stripe

```env
STRIPE_KEY=pk_live_CHANGE_ME
STRIPE_SECRET=sk_live_CHANGE_ME
CASHIER_CURRENCY=eur
CASHIER_CURRENCY_LOCALE=fr_BE
STRIPE_CONNECT_WEBHOOK_SECRET=whsec_CHANGE_ME
BRIO_PLATFORM_FEE_PERCENT=15
STRIPE_CONNECT_ENABLED=true
```

## Broadcasting (Laravel Reverb)

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=brio
REVERB_APP_KEY=CHANGE_ME
REVERB_APP_SECRET=CHANGE_ME
REVERB_HOST=realtime.brio.com
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

## KYC (Onfido — leave empty for mock mode in staging)

```env
ONFIDO_API_TOKEN=CHANGE_ME
ONFIDO_BASE_URL=https://api.eu.onfido.com/v3.6
KYC_AUTO_APPROVE=false
KYC_DRIVER=onfido
```

## AI (Claude Vision for photo quotes)

```env
ANTHROPIC_API_KEY=CHANGE_ME
```

## Sentry

```env
SENTRY_LARAVEL_DSN=CHANGE_ME
SENTRY_TRACES_SAMPLE_RATE=0.1
```

## SMS (Twilio — leave empty for mock mode)

```env
TWILIO_SID=CHANGE_ME
TWILIO_AUTH_TOKEN=CHANGE_ME
TWILIO_FROM=+32XXXXXXXXX
SMS_DRIVER=twilio
```

## Push Notifications (FCM)

```env
FCM_SERVER_KEY=CHANGE_ME
PUSH_DRIVER=fcm
```

## Google Calendar Integration

```env
GOOGLE_CLIENT_ID=CHANGE_ME
GOOGLE_CLIENT_SECRET=CHANGE_ME
GOOGLE_REDIRECT_URI=https://app.brio.com/auth/google/callback
```

## Matching / Dispatch

```env
MATCHING_ENABLED=true
MATCHING_SHADOW_MODE=false
```

## CORS

```env
CORS_ALLOWED_ORIGINS=https://app.brio.com,https://provider.brio.com
```

## Filesystem

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=CHANGE_ME
AWS_SECRET_ACCESS_KEY=CHANGE_ME
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=brio-production
AWS_URL=https://cdn.brio.com
```

## Misc

```env
SANCTUM_STATEFUL_DOMAINS=app.brio.com,provider.brio.com
SESSION_DOMAIN=.brio.com
```
