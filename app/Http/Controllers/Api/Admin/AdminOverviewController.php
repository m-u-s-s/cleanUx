<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\KycVerification;
use App\Models\Mission;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\AdminScope;
use App\Support\Domain\MissionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/** Les indicateurs d'accueil de la console d'administration mobile. */
class AdminOverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // LE PÉRIMÈTRE DE ZONE VAUT AUSSI ICI.
        $admin = Auth::user();

        return response()->json([
            'ok' => true,
            'kpis' => [
                $this->kpi('users', 'Comptes', 'people-outline',
                    fn () => AdminScope::scopeUserQuery(User::query(), $admin)->count()),

                $this->kpi('bookings_pending', 'Réservations en attente', 'hourglass-outline',
                    fn () => AdminScope::scopeRendezVousQuery(Booking::pending(), $admin)->count()),

                $this->kpi('bookings_today', 'Réservations du jour', 'today-outline',
                    fn () => AdminScope::scopeRendezVousQuery(
                        Booking::whereDate('scheduled_date', today()), $admin
                    )->count()),

                $this->kpi('missions_active', 'Missions en cours', 'briefcase-outline',
                    fn () => Mission::whereIn('status', MissionStatus::trackable())->count()),

                // DEUX MODÈLES DE LITIGE COEXISTENT, et ce compteur doit désigner le bon.
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
