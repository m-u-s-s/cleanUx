<?php

namespace App\Sentry;

/**
 * Filtre `before_send` de Sentry, sorti de config/sentry.php.
 *
 * POURQUOI CETTE CLASSE EXISTE
 * `php artisan config:cache` sérialise toute la configuration avec var_export().
 * Une closure ne s'exporte pas : la commande échouait avec « the value at
 * "sentry.before_send" is non-serializable » et aucun déploiement ne pouvait aboutir.
 * Un tableau [classe, méthode] fait de deux chaînes, lui, s'exporte sans problème.
 *
 * POURQUOI LE POINT D'ENTRÉE DÉCLARÉ EST `handle()` ET NON `__invoke()`
 * PHP 8 refuse d'appeler une méthode d'instance de façon statique : [self::class,
 * '__invoke'] n'est donc PAS un callable (is_callable() rend false, vérifié sur PHP 8.5),
 * et `__invoke` ne peut pas être déclarée statique (erreur fatale sur les méthodes
 * magiques). Or Sentry valide l'option `before_send` avec le type `callable`
 * (Sentry\Options::configureOptions → setAllowedTypes('before_send', ['callable'])) :
 * une valeur non appelable ferait échouer la construction du client. `handle()` est
 * donc le point d'entrée statique, et il délègue à `__invoke()` pour que la classe
 * reste invokable.
 *
 * SIGNATURE ATTENDUE
 * Sentry\Client::applyBeforeSendCallback() appelle le filtre avec
 * (\Sentry\Event $event, ?\Sentry\EventHint $hint). Les deux paramètres sont laissés
 * sans type ici pour conserver À L'IDENTIQUE le garde défensif hérité de la closure
 * d'origine (qui vérifiait elle-même is_object() / method_exists()).
 */
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
