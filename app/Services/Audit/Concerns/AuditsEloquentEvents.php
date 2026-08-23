<?php

namespace App\Services\Audit\Concerns;

use App\Services\Audit\AuditService;
use Illuminate\Database\Eloquent\Model;

/** Trait à utiliser sur les modèles Eloquent "critiques" pour audit auto sur created / updated / deleted. */
trait AuditsEloquentEvents
{
    public static function bootAuditsEloquentEvents(): void
    {
        // Le modele recu porte forcement ce trait — c'est lui qui enregistre les ecouteurs. Le
        // dire explicitement evite que chaque modele adoptant le trait ajoute trois erreurs
        // d'analyse « methode inconnue » sur du code parfaitement correct.
        static::created(function (Model $model) {
            /** @var Model&self $model */
            $model->writeAuditEvent('created', null);
        });

        static::updated(function (Model $model) {
            /** @var Model&self $model */
            $changes = $model->getChanges();
            if (empty($changes)) {
                return;
            }
            $model->writeAuditEvent('updated', $changes);
        });

        static::deleted(function (Model $model) {
            /** @var Model&self $model */
            $model->writeAuditEvent('deleted', null);
        });
    }

    /** @param  array<string, mixed>|null  $changes */
    public function writeAuditEvent(string $action, ?array $changes): void
    {
        try {
            /**
             * Le garde reste indispensable : ce trait sert des dizaines de modèles et seuls quelques-uns définissent ces méthodes.
             *
             * @phpstan-ignore function.alreadyNarrowedType
             */
            $domain = method_exists($this, 'auditEventDomain')
                ? $this->auditEventDomain()
                : strtolower(class_basename(static::class));

            $whitelist = method_exists($this, 'auditedAttributes')
                ? (array) $this->auditedAttributes()
                : null;

            $context = ['model_id' => $this->getKey()];
            if ($changes !== null) {
                if ($whitelist) {
                    $changes = array_intersect_key($changes, array_flip($whitelist));
                }
                $context['changes'] = $changes;
            }

            $eventType = $domain.'.'.$action;

            app(AuditService::class)->record($eventType, $context, [
                'subject' => $this,
                'domain' => $domain,
            ]);
        } catch (\Throwable $e) {
            // soft-fail : audit ne doit JAMAIS bloquer la write
        }
    }
}
