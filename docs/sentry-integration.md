# Sentry — Error tracking & Performance monitoring

Date : 2026-05-20. Non installé par défaut — guide d'activation.

## Installation

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn="https://...@sentry.io/..."
```

Cette commande crée `config/sentry.php` et ajoute `SENTRY_LARAVEL_DSN` au `.env.example`.

## Configuration recommandée pour CleanUx

```env
SENTRY_LARAVEL_DSN=https://...@sentry.io/...
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_PROFILES_SAMPLE_RATE=0.1
SENTRY_ENVIRONMENT="${APP_ENV}"
SENTRY_SEND_DEFAULT_PII=false
```

`SENTRY_SEND_DEFAULT_PII=false` est crucial RGPD : par défaut Sentry envoie l'IP + email user des erreurs, ce qu'on ne veut pas.

## Tags & contexte automatique à brancher

Dans `app/Providers/AppServiceProvider.php::boot()` :

```php
if (app()->bound('sentry')) {
    \Sentry\configureScope(function (\Sentry\State\Scope $scope) {
        $scope->setTag('app.module', 'cleanux-v2');
        if ($tenant = app(\App\Services\TenancyV2\TenantContext::class)->current()) {
            $scope->setTag('tenant.code', $tenant->code);
            $scope->setTag('tenant.plan', $tenant->plan_code);
        }
    });
}
```

## Breadcrumbs déjà utiles (sans config supplémentaire)

Les modules v2 émettent déjà des breadcrumbs implicites via `Log::warning()` qui apparaîtront dans Sentry :
- `[business_webhook] emit failed` (BusinessEventEmitter)
- `[accounting_auto_post] sale|payment|refund failed` (BookingAutoPoster)
- `[chat_auto] ensureThreadForBooking failed` (BookingChatAutoCreator)
- `[critical_audit] failed` (CriticalActionAuditor)
- `[kyb_v2] insee|vies|companies_house error`
- `[geo_v2] google|mapbox error`
- `[webhooks_v2] delivery http error`
- `[subscriptions_v2] stripe charge error`

## Filtres Sentry recommandés

Pour ne pas surcharger Sentry avec les erreurs attendues (soft-fail) :
```php
// config/sentry.php
'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
    $msg = $event->getMessage();
    // Filtrer les soft-fails non-actionables
    foreach (['[business_webhook]', '[chat_auto]', '[critical_audit]'] as $prefix) {
        if (str_contains((string) $msg, $prefix)) {
            return null;
        }
    }
    return $event;
},
```

## Alerts à configurer dans Sentry Dashboard

1. **Webhook delivery failures > 50/5min** → notif Slack
2. **Subscription billing failures > 10/day** → email admin
3. **`accounting.period_reopened` event** (audit critical) → email admin immédiat
4. **`kyb.entity_rejected` event** → notif compliance team
5. **`api_token.suspended` event** → notif security team
6. **Uncaught exceptions level=error** → PagerDuty si oncall

## Health check Sentry

```bash
php artisan sentry:test
```

Doit retourner `Sending test event...` puis tu vois l'event dans Sentry UI ~30s plus tard.
