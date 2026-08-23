<?php

namespace App\Sentry;

/** Filtre `before_send` de Sentry, sorti de config/sentry.php. */
class BeforeSend
{
    /**
     * Point d'entrée statique référencé par config/sentry.php.
     *
     * @param  mixed  $event  L'événement Sentry (\Sentry\Event en pratique)
     * @param  mixed  $hint  Le \Sentry\EventHint transmis par le SDK ; inutilisé ici
     * @return mixed L'événement à envoyer, ou null pour l'écarter
     */
    public static function handle($event, $hint = null)
    {
        return (new self)($event, $hint);
    }

    /**
     * Ignore les soft-fail breadcrumbs attendus.
     *
     * @param  mixed  $event  L'événement Sentry (\Sentry\Event en pratique)
     * @param  mixed  $hint  Le \Sentry\EventHint transmis par le SDK ; inutilisé ici
     * @return mixed L'événement à envoyer, ou null pour l'écarter
     */
    public function __invoke($event, $hint = null)
    {
        if (! is_object($event) || ! method_exists($event, 'getMessage')) {
            return $event;
        }
        $msg = (string) $event->getMessage();
        $ignoredPrefixes = [
            '[business_webhook]',
            '[chat_auto]',
            '[critical_audit]',
            '[accounting_auto_post]',
            '[fleet_v2]',
            '[geo_v2]',
            '[trip_tracking]',
            '[loyalty_redemption]',
            '[tips]',
            '[presence_auto]',
        ];
        foreach ($ignoredPrefixes as $prefix) {
            if (str_starts_with($msg, $prefix)) {
                return null;
            }
        }

        return $event;
    }
}
