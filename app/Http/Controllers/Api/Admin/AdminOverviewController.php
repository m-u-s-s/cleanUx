<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\KycVerification;
use App\Models\Mission;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\Domain\MissionStatus;
use Illuminate\Http\JsonResponse;

/**
 * Les indicateurs d'accueil de la console d'administration mobile.
 *
 * SEPT NOMBRES, PAS UN TABLEAU DE BORD. Un accueil de téléphone se lit en trois secondes : sept
 * compteurs exacts y valent mieux qu'une batterie de graphiques dont on ne sait plus ce qu'ils
 * mesurent. Les analyses fines restent sur les pages dédiées.
 *
 * CHAQUE COMPTEUR S'ADOSSE À LA SOURCE D'AUTORITÉ DU DOMAINE — `Booking::PENDING_STATUSES`,
 * `MissionStatus::trackable()`, `KycVerification::scopePending()` — jamais à des chaînes
 * recopiées. La colonne `bookings.status` porte historiquement des valeurs françaises ET
 * anglaises : compter sur une seule des deux formes donnerait un chiffre faux qui a l'air juste.
 *
 * UN COMPTEUR QUI ÉCHOUE VAUT ZÉRO, PAS UNE PANNE D'ACCUEIL. Les tables citées ici sont posées par
 * des migrations de modules distincts ; sur un environnement où l'une manque, l'administrateur
 * doit garder ses six autres chiffres plutôt que perdre l'écran entier.
 */
class AdminOverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'kpis' => [
                $this->kpi('users', 'Comptes', 'people-outline',
                    fn () => User::count()),

                $this->kpi('bookings_pending', 'Réservations en attente', 'hourglass-outline',
                    fn () => Booking::pending()->count()),

                $this->kpi('bookings_today', 'Réservations du jour', 'today-outline',
                    fn () => Booking::whereDate('scheduled_date', today())->count()),

                $this->kpi('missions_active', 'Missions en cours', 'briefcase-outline',
                    fn () => Mission::whereIn('status', MissionStatus::trackable())->count()),

                /*
                 * DEUX MODÈLES DE LITIGE COEXISTENT, et ce compteur doit désigner le bon.
                 *
                 * `ComplaintCase` (complaint_cases) est celui de la page admin « Litiges » et du
                 * `DisputeResolutionService` ; `CustomerClaim` (customer_claims) est un modèle
                 * parallèle. Compter le second afficherait un chiffre qui ne correspond à rien de
                 * ce qu'un administrateur peut ouvrir depuis cet écran.
                 *
                 * Le complément plutôt que la liste : un statut ajouté demain doit apparaître
                 * comme ouvert, pas disparaître silencieusement du compteur.
                 */
                $this->kpi('claims_open', 'Litiges ouverts', 'alert-circle-outline',
                    fn () => ComplaintCase::whereNotIn('status', [
                        ComplaintCase::STATUS_RESOLVED,
                        ComplaintCase::STATUS_CLOSED,
                    ])->count()),

                $this->kpi('kyc_pending', 'KYC à traiter', 'finger-print-outline',
                    fn () => KycVerification::pending()->count()),

                $this->kpi('providers_pending', 'Prestataires à valider', 'person-add-outline',
                    fn () => ProviderProfile::where('status', 'pending')->count()),
            ],
        ]);
    }

    /**
     * Un compteur, ou zéro si sa table n'existe pas sur cet environnement.
     *
     * @param  callable(): int  $count
     * @return array{key: string, label: string, icon: string, value: int, available: bool}
     */
    private function kpi(string $key, string $label, string $icon, callable $count): array
    {
        $available = true;
        $value = 0;

        try {
            $value = $count();
        } catch (\Throwable) {
            // `available: false` distingue « zéro mesuré » de « pas mesurable ». Afficher un 0
            // franc pour une table absente ferait croire à un calme qui n'existe pas.
            $available = false;
        }

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'value' => $value,
            'available' => $available,
        ];
    }
}
