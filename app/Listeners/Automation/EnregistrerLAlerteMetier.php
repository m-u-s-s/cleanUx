<?php

namespace App\Listeners\Automation;

use App\Events\BusinessAlertRaised;
use App\Models\AlerteMetier;
use App\Services\Automation\FileDeReevaluation;
use App\Services\Automation\Registre\DeclencheurRegistre;

/** Persiste l'alerte, puis depose une reevaluation. Rien d'autre : la requete attend. */
class EnregistrerLAlerteMetier
{
    /**
     * Ce que chaque alerte DESIGNE, decide par sa cle. Un balayage du contexte se
     * tromperait : l'alerte de mission bloquee porte AUSSI un `booking_id`.
     *
     * @var array<string, array{entite: string, champ: string}|null>
     */
    private const ENTITE_LIEE = [
        'payment_capture_failed' => ['entite' => 'booking', 'champ' => 'booking_id'],
        'stuck_mission_holding_funds' => ['entite' => 'mission', 'champ' => 'mission_id'],
        'payout_failed' => null,
        'webhook_backlog' => null,
        'reconciliation_divergence' => null,
    ];

    public function __construct(
        protected FileDeReevaluation $file,
        protected DeclencheurRegistre $declencheurs,
    ) {}

    public function handle(BusinessAlertRaised $evenement): void
    {
        $alerte = AlerteMetier::create([
            'cle' => $evenement->key,
            'niveau' => $evenement->level,
            'message' => $evenement->message,
            'contexte' => $evenement->context,
            'entite_type' => $this->typeLie($evenement),
            'entite_id' => $this->identifiantLie($evenement),
            'levee_le' => now(),
        ]);

        foreach ($this->declencheurs->pourEvenement($evenement) as $declencheur) {
            $this->file->deposer($declencheur->cle(), $declencheur->entite(), $alerte->id);
        }
    }

    private function typeLie(BusinessAlertRaised $evenement): ?string
    {
        return self::ENTITE_LIEE[$evenement->key]['entite'] ?? null;
    }

    private function identifiantLie(BusinessAlertRaised $evenement): ?int
    {
        $decision = self::ENTITE_LIEE[$evenement->key] ?? null;

        return $decision === null ? null : ($evenement->context[$decision['champ']] ?? null);
    }
}
