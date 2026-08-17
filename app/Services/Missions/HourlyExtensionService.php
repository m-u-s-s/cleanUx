<?php

namespace App\Services\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use App\Services\OrderEngine\HourlyRateResolver;
use App\Support\Domain\MissionStatus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * PROLONGER — la décision du client d'acheter du temps en plus, au tarif normal.
 *
 * Elle est possible AVANT et PENDANT l'intervention, et c'est tout l'intérêt : personne ne sait à
 * l'avance qu'un appartement demandera quatre heures plutôt que trois. Ce qui la distingue du
 * dépassement, c'est qu'elle est DÉCIDÉE — donc facturée au tarif normal, sans la majoration.
 *
 * LA FENÊTRE SE FERME À LA FIN DE LA FRANCHISE, et pas plus tard. Sans cette limite, un client
 * attendrait la fin de l'intervention pour prolonger rétroactivement : il paierait les heures,
 * jamais la majoration, et la majoration ne majorerait plus rien. La franchise de quinze minutes
 * fait donc double emploi, à dessein — c'est le temps offert, et c'est le dernier moment pour
 * décider.
 *
 * PASSÉ CETTE FENÊTRE, ON NE REFUSE PAS LE SERVICE : le prestataire continue et le temps
 * supplémentaire est facturé automatiquement, majoré et plafonné. Prolonger n'aurait plus d'objet,
 * puisque le temps est déjà couvert.
 *
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 *
 * LE PIÈGE QUI A DICTÉ TOUTE LA CONCEPTION : **on n'écrit QUE `purchased_minutes`.**
 *
 * `duree_estimee` est la BASE DU CALCUL DU TARIF. `HourlyRateResolver` déduit le tarif horaire
 * réellement payé en divisant le montant autorisé par cette durée — c'est ainsi qu'il retrouve les
 * majorations que le moteur de prix applique puis oublie. Faire suivre `duree_estimee` à chaque
 * prolongation ferait donc glisser le tarif déduit à chaque fois : trois heures à 58,50 € devenues
 * quatre heures rendraient un tarif de 43,88 €, et le client paierait sa quatrième heure moins cher
 * que la première — puis le dépassement serait majoré sur cette base erodée.
 *
 * Les deux colonnes portent bien DEUX notions : `duree_estimee` est ce qui a été VENDU au moment de
 * l'autorisation, `purchased_minutes` est ce qui est DÛ aujourd'hui. Elles coïncident jusqu'à la
 * première prolongation, et c'est exactement ce qui rend la confusion facile.
 */
class HourlyExtensionService
{
    public function __construct(
        private readonly HourlyRateResolver $rates,
        private readonly HourlyMissionClock $horloge,
    ) {}

    /**
     * Ajoute du temps acheté à une réservation vendue au temps.
     *
     * @return array<string, mixed> l'état de l'horloge après prolongation, tel que les écrans le lisent
     *
     * @throws RuntimeException quand la prolongation n'est pas recevable — le message est destiné
     *                          au client, il doit rester lisible
     */
    public function prolonger(Booking $booking, int $minutes, ?User $auteur = null): array
    {
        $mission = $this->missionDe($booking);

        $this->refuserSiImpossible($booking, $mission, $minutes);

        $avant = (int) ($booking->purchased_minutes ?? 0);
        $apres = $avant + $minutes;

        DB::transaction(function () use ($booking, $apres, $avant, $minutes, $auteur) {
            /*
             * ÉCRITURE SANS ÉVÉNEMENT — et ici c'est un choix de coût, pas de correction.
             *
             * `RendezVousObserver::saved()` déclenche sur toute sauvegarde d'une réservation
             * confirmée une resynchronisation complète : géocodage, checklist, SLA,
             * auto-assignation. Acheter une demi-heure n'a aucune raison de provoquer tout cela.
             */
            Booking::query()->whereKey($booking->getKey())->update(['purchased_minutes' => $apres]);

            $booking->forceFill(['purchased_minutes' => $apres]);

            $this->journaliser($booking, $avant, $apres, $minutes, $auteur);
        });

        /*
         * On relit la mission : l'horloge lit `mission->booking`, et une relation déjà chargée
         * porterait encore l'ancienne valeur — le client verrait sa prolongation refusée par
         * l'écran qu'il vient de faire changer.
         */
        return $mission !== null
            ? $this->horloge->etat($mission->fresh(['booking']) ?? $mission)
            : ['applies' => false];
    }

    /**
     * Cette réservation accepte-t-elle encore une prolongation, et jusqu'à combien ?
     *
     * Rend `null` quand elle n'est pas vendue au temps. Sert aux écrans, qui doivent savoir
     * MONTRER le bouton avant que quiconque appuie dessus.
     *
     * @return array{allowed: bool, reason: string|null, max_minutes: int, increment_minutes: int, options: list<array{minutes: int, label: string, amount_cents: int|null}>}|null
     */
    public function etatDeLaProlongation(Booking $booking): ?array
    {
        if (! $this->rates->seFactureALHeure($booking)) {
            return null;
        }

        $motif = $this->motifDeRefus($booking, $this->missionDe($booking));
        $marge = $this->marge($booking);

        return [
            'allowed' => $motif === null,
            'reason' => $motif,
            'max_minutes' => $marge,
            'increment_minutes' => $this->increment(),
            'options' => $this->options($booking, $marge),
        ];
    }

    /**
     * LES CHOIX PROPOSÉS, AVEC LEUR PRIX — calculé ici, jamais par l'application.
     *
     * Le client voit un montant AVANT de confirmer : c'est sur ce chiffre qu'il décide. Le laisser
     * fabriquer par l'écran, fût-ce par une multiplication triviale, créerait un second prix pour
     * la même prestation — et c'est celui de l'appareil que le client aurait lu au moment de dire
     * oui. Le serveur propose, le téléphone affiche.
     *
     * @return list<array{minutes: int, label: string, amount_cents: int|null}>
     */
    private function options(Booking $booking, int $marge): array
    {
        $pas = $this->increment();
        $tarif = $this->rates->tarifEffectifDeLaReservation($booking);

        // Un pas, deux pas, quatre pas — soit une demi-heure, une heure et deux heures au réglage
        // par défaut. Au-delà, on répète le geste : proposer huit choix, c'est n'en proposer aucun.
        $choix = [];

        foreach ([1, 2, 4] as $facteur) {
            $minutes = $pas * $facteur;

            if ($minutes > $marge) {
                continue;
            }

            $choix[] = [
                'minutes' => $minutes,
                'label' => $this->enHeures($minutes),
                'amount_cents' => $tarif !== null ? (int) round($tarif * ($minutes / 60)) : null,
            ];
        }

        return $choix;
    }

    // ─────────────────────────────────────────────────────────────────────

    private function refuserSiImpossible(Booking $booking, ?Mission $mission, int $minutes): void
    {
        $motif = $this->motifDeRefus($booking, $mission);

        if ($motif !== null) {
            throw new RuntimeException($motif);
        }

        $pas = $this->increment();

        if ($minutes <= 0 || $minutes % $pas !== 0) {
            throw new RuntimeException("La prolongation se fait par tranches de {$pas} minutes.");
        }

        $marge = $this->marge($booking);

        if ($minutes > $marge) {
            throw new RuntimeException(
                $marge > 0
                    ? 'Vous pouvez prolonger de '.$this->enHeures($marge).' au maximum.'
                    : 'La durée maximale de cette prestation est atteinte.'
            );
        }
    }

    /** Le motif de refus, ou `null` si la prolongation est recevable. */
    private function motifDeRefus(Booking $booking, ?Mission $mission): ?string
    {
        if (! $this->rates->seFactureALHeure($booking)) {
            return 'Cette prestation n’est pas facturée à l’heure.';
        }

        if ((int) ($booking->purchased_minutes ?? 0) <= 0) {
            return 'Aucune durée n’a été achetée sur cette réservation.';
        }

        if ($mission === null) {
            // Avant qu'une mission n'existe, il n'y a rien qui court : prolonger reste possible.
            return null;
        }

        if (in_array($mission->status, [MissionStatus::COMPLETED, MissionStatus::CANCELLED], true)) {
            return 'L’intervention est terminée.';
        }

        // Pas encore démarrée : aucune horloge ne court, la prolongation est toujours « à temps ».
        if ($mission->actual_start_at === null) {
            return null;
        }

        $etat = $this->horloge->etat($mission);

        if (($etat['applies'] ?? false) !== true) {
            return null;
        }

        $depassement = (int) ($etat['overrun_minutes'] ?? 0);
        $franchise = (int) ($etat['grace_minutes'] ?? 0);

        if ($depassement > $franchise) {
            return 'Le temps supplémentaire est déjà en cours de facturation : il sera ajouté automatiquement à la fin, au tarif majoré.';
        }

        return null;
    }

    /** Combien de minutes peuvent encore être achetées avant le plafond de la prestation. */
    private function marge(Booking $booking): int
    {
        $maximum = (int) round((float) Config::get('order_engine.hourly_max_hours', 12.0) * 60);
        $achetees = (int) ($booking->purchased_minutes ?? 0);

        return max(0, $maximum - $achetees);
    }

    private function increment(): int
    {
        return max(1, (int) round((float) Config::get('order_engine.hourly_step_hours', 0.5) * 60));
    }

    private function enHeures(int $minutes): string
    {
        $heures = intdiv($minutes, 60);
        $reste = $minutes % 60;

        if ($heures <= 0) {
            return $reste.' min';
        }

        return $reste === 0 ? $heures.' h' : $heures.' h '.$reste;
    }

    private function missionDe(Booking $booking): ?Mission
    {
        return Mission::query()->where('booking_id', $booking->getKey())->latest('id')->first();
    }

    /**
     * LA TRACE, ET POURQUOI ELLE N'EST PAS OPTIONNELLE.
     *
     * Une prolongation change ce que le client doit. Sans journal, un litige sur la facture se
     * réglerait en comparant deux souvenirs : le sien et celui du prestataire. On garde qui, quand,
     * combien — et le montant du moment, parce que le tarif se déduit et pourrait se relire
     * autrement un an plus tard.
     */
    private function journaliser(Booking $booking, int $avant, int $apres, int $minutes, ?User $auteur): void
    {
        $tarif = $this->rates->tarifEffectifDeLaReservation($booking);

        // `metadata` est castée en tableau par le modèle ; seul le `null` d'une colonne jamais
        // écrite doit être rattrapé. La clé, elle, peut contenir n'importe quoi — un journal écrit
        // par une version antérieure, ou une valeur posée à la main en base.
        $journal = $booking->metadata ?? [];
        $lignes = is_array($journal['prolongations'] ?? null) ? $journal['prolongations'] : [];

        $lignes[] = [
            'a' => now()->toIso8601String(),
            'par' => $auteur?->getKey(),
            'minutes' => $minutes,
            'minutes_avant' => $avant,
            'minutes_apres' => $apres,
            'tarif_horaire_cents' => $tarif,
            'montant_cents' => $tarif !== null ? (int) round($tarif * ($minutes / 60)) : null,
        ];

        $journal['prolongations'] = $lignes;

        Booking::query()->whereKey($booking->getKey())->update(['metadata' => $journal]);

        $booking->forceFill(['metadata' => $journal]);
    }
}
