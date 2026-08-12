<?php

namespace Tests\Feature\Ops;

use Closure;
use PHPUnit\Framework\Attributes\Test;
use Sentry\Event;
use Tests\TestCase;

/**
 * Garde-fou de déploiement.
 *
 * `php artisan config:cache` écrit la configuration avec var_export() puis relit le
 * fichier produit. Une closure ne s'exporte pas : la commande échoue alors avec
 * « Your configuration files could not be serialized because the value at "x.y" is
 * non-serializable », et plus AUCUN déploiement ne peut aboutir.
 *
 * Ce test reproduit exactement cette cause SANS lancer config:cache : la commande
 * inspecte `$app['config']->all()` (cf. ConfigCacheCommand::getFreshConfiguration()),
 * on inspecte la même chose. On évite ainsi d'écrire dans bootstrap/cache/, partagé
 * avec les autres agents et avec les suites de tests en cours.
 */
class ConfigSerialisableTest extends TestCase
{
    #[Test]
    public function aucune_valeur_de_configuration_n_est_une_closure(): void
    {
        $fautives = $this->cheminsDesClosures(config()->all());

        $this->assertSame(
            [],
            $fautives,
            '`php artisan config:cache` échouerait : ces clés de configuration portent une '
            ."closure, que var_export() ne sait pas écrire. Sortir la logique dans une classe\n"
            ."et référencer [Classe::class, 'methodeStatique'] à la place.\n"
            .'Clés fautives : '.implode(', ', $fautives),
        );
    }

    /**
     * Sérialisable ne suffit pas : Sentry valide `before_send` avec le type `callable`
     * (Sentry\Options::configureOptions). Une valeur exportable mais non appelable ferait
     * échouer la construction du client — pire que le bug d'origine, car silencieuse au
     * moment du déploiement.
     */
    #[Test]
    public function le_filtre_sentry_before_send_reste_appelable(): void
    {
        $filtre = config('sentry.before_send');

        $this->assertIsCallable(
            $filtre,
            'sentry.before_send doit rester un callable : le SDK Sentry refuse de construire '
            .'son client sinon, et plus aucun événement ne remonte.',
        );
    }

    /**
     * Le va-et-vient var_export() → eval() est littéralement ce que fait
     * ConfigCacheCommand quand il diagnostique une valeur fautive.
     */
    #[Test]
    public function la_valeur_du_filtre_sentry_survit_a_var_export(): void
    {
        $export = var_export(config('sentry.before_send'), true);

        /** @var mixed $reconstruit */
        $reconstruit = eval("return {$export};");

        $this->assertIsCallable(
            $reconstruit,
            'La valeur de sentry.before_send doit rester appelable APRÈS être passée par le '
            .'cache de configuration, pas seulement avant.',
        );
    }

    /**
     * Le comportement de filtrage lui-même, appelé avec la signature réelle du SDK :
     * Sentry\Client::applyBeforeSendCallback() passe (\Sentry\Event, ?\Sentry\EventHint).
     */
    #[Test]
    public function le_filtre_sentry_ecarte_les_soft_fails_attendus(): void
    {
        $filtre = config('sentry.before_send');

        $softFail = Event::createEvent();
        $softFail->setMessage('[tips] bonus loyalty indisponible');

        $this->assertNull(
            $filtre($softFail, null),
            'Un soft-fail attendu doit être écarté avant l\'envoi à Sentry.',
        );
    }

    #[Test]
    public function le_filtre_sentry_laisse_passer_les_vrais_incidents(): void
    {
        $filtre = config('sentry.before_send');

        $incident = Event::createEvent();
        $incident->setMessage('Capture Stripe refusée sur la réservation #42');

        $this->assertSame(
            $incident,
            $filtre($incident, null),
            'Un incident réel doit continuer à remonter : un filtre trop large ferait perdre '
            .'tous les événements en silence.',
        );
    }

    /**
     * Parcourt la configuration en profondeur et rend le chemin pointé de chaque closure.
     *
     * @param  array<array-key, mixed>  $valeurs
     * @return list<string>
     */
    private function cheminsDesClosures(array $valeurs, string $prefixe = ''): array
    {
        $chemins = [];

        foreach ($valeurs as $cle => $valeur) {
            $chemin = $prefixe === '' ? (string) $cle : $prefixe.'.'.$cle;

            if ($valeur instanceof Closure) {
                $chemins[] = $chemin;

                continue;
            }

            if (is_array($valeur)) {
                foreach ($this->cheminsDesClosures($valeur, $chemin) as $enfant) {
                    $chemins[] = $enfant;
                }
            }
        }

        return $chemins;
    }
}
