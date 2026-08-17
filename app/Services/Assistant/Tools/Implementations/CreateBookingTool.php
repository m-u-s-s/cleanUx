<?php

namespace App\Services\Assistant\Tools\Implementations;

use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\Assistant\Tools\Contracts\AssistantTool;
use App\Services\International\CountryMarketResolver;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Tool d'écriture : créer une nouvelle réservation.
 *
 * REVIEW FIX :
 *   - Génère booking_reference (colonne UNIQUE NOT NULL sans default)
 *     → sans cette ligne, Booking::create() plantait avec
 *       "Field 'booking_reference' doesn't have a default value"
 *   - Utilise la vraie route (client.rendezvous.index) qui existe dans
 *     routes/client.php au lieu de client.bookings.show qui n'existe pas
 *
 * executesImmediately = FALSE → l'orchestrateur enregistre une AssistantAction
 * en pending_confirmation et l'utilisateur doit valider dans l'UI avant
 * que la réservation soit réellement créée.
 */
class CreateBookingTool implements AssistantTool
{
    public function name(): string
    {
        return 'create_booking';
    }

    public function description(): string
    {
        return "Crée une nouvelle réservation après confirmation explicite de l'utilisateur. "
            ."Tu dois TOUJOURS demander à l'utilisateur la date, l'heure, le type de lieu (appartement/maison/bureau), "
            ."et la surface approximative AVANT d'appeler ce tool. "
            ."Le tool ne crée pas immédiatement : il prépare une demande qui sera confirmée par l'utilisateur dans l'UI.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'service_catalog_id' => [
                    'type' => 'integer',
                    'description' => "Identifiant du service du catalogue. Si tu ne connais pas, utilise list_services_catalog d'abord.",
                ],
                'service_slug' => [
                    'type' => 'string',
                    'description' => "Alternative au service_catalog_id : slug du service (ex: 'cleaning-home-standard').",
                ],
                'scheduled_date' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Date au format YYYY-MM-DD.',
                ],
                'scheduled_time' => [
                    'type' => 'string',
                    'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$',
                    'description' => 'Heure au format HH:MM (24h).',
                ],
                'place_type' => [
                    'type' => 'string',
                    'enum' => ['apartment', 'house', 'office', 'shop', 'other'],
                    'description' => 'Type de lieu.',
                ],
                'surface_m2' => [
                    'type' => 'integer',
                    'minimum' => 5,
                    'maximum' => 5000,
                    'description' => 'Surface approximative en m².',
                ],
                'address' => [
                    'type' => 'string',
                    'description' => 'Adresse complète (rue + numéro).',
                ],
                'city' => [
                    'type' => 'string',
                    'description' => 'Ville.',
                ],
                'postal_code' => [
                    'type' => 'string',
                    'description' => 'Code postal.',
                ],
                'frequency' => [
                    'type' => 'string',
                    'enum' => ['unique', 'weekly', 'biweekly', 'monthly'],
                    'description' => "Fréquence : 'unique' = une fois, sinon récurrent.",
                ],
                'customer_comment' => [
                    'type' => 'string',
                    'description' => 'Note libre du client (étages, accès, animaux…).',
                ],
            ],
            'required' => ['scheduled_date', 'scheduled_time', 'place_type', 'surface_m2', 'address', 'city', 'postal_code'],
        ];
    }

    public function authorize(User $user): bool
    {
        // Tout utilisateur authentifié peut créer une réservation pour lui-même
        // Pour entreprise : seuls les rôles habilités (cf. PermissionService.bookings.create)
        if ($user->organization_account_id) {
            return app(PermissionService::class)
                ->can($user, 'bookings.create', $user->currentOrganization);
        }

        return true;
    }

    public function executesImmediately(): bool
    {
        // Création de booking → demande confirmation
        return false;
    }

    public function execute(User $user, array $input): array
    {
        // Résolution service_catalog_id : on accepte slug ou id
        $serviceId = $input['service_catalog_id'] ?? null;
        if (! $serviceId && ! empty($input['service_slug'])) {
            $serviceId = ServiceCatalog::where('slug', $input['service_slug'])->value('id');
        }

        // ⚙ REVIEW FIX : Génère booking_reference (UNIQUE, NOT NULL, pas de default).
        // Format aligné avec HandlesBookingCreation::makeReference (CUX-XXXXXX).
        $reference = $this->generateUniqueBookingReference();

        $booking = Booking::create([
            'booking_reference' => $reference,
            'customer_user_id' => $user->id,
            'client_id' => $user->id, // legacy alias
            'service_catalog_id' => $serviceId,
            'scheduled_date' => $input['scheduled_date'],
            'scheduled_time' => $input['scheduled_time'],
            'place_type' => $input['place_type'],
            'surface_m2' => (int) $input['surface_m2'],
            'address' => $input['address'],
            'city' => $input['city'],
            'postal_code' => $input['postal_code'],
            'country' => $input['country'] ?? 'BE',
            'frequency' => $input['frequency'] ?? 'unique',
            'customer_comment' => $input['customer_comment'] ?? null,
            'status' => 'pending',
            'booking_mode' => 'assistant',
            'created_by' => $user->id,
            // Meme autorite que les deux autres chemins de creation : la devise se deduit de la
            // position, jamais d'une constante. L'assistant n'a pas de zone resolue sous la main,
            // il passe donc le code pays de l'adresse -- le resolveur sait quoi en faire.
            'currency' => app(CountryMarketResolver::class)->deviseAttendue(
                client: $user,
                isoPays: $input['country'] ?? 'BE',
            ),
            'customer_organization_id' => $user->organization_account_id,
        ]);

        return [
            'ok' => true,
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'message' => "Réservation créée. Référence : {$booking->booking_reference}",
            // ⚙ REVIEW FIX : route('client.bookings.show') n'existait pas dans
            // routes/client.php. La vraie route pour voir ses RDV est
            // 'client.rendezvous.index' (Livewire MesRendezVousClient).
            'view_url' => $this->resolveBookingViewUrl($booking),
        ];
    }

    /**
     * Génère une référence unique au format CUX-XXXXXX (6 chars alphanum upper).
     * Boucle jusqu'à trouver une combinaison libre (collision proba ≈ 0 en pratique).
     */
    protected function generateUniqueBookingReference(int $maxAttempts = 8): string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $candidate = 'CUX-'.strtoupper(Str::random(6));
            if (! Booking::where('booking_reference', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Fallback ULID (extrêmement peu probable d'arriver ici)
        return 'CUX-'.strtoupper(substr((string) Str::ulid(), -8));
    }

    /**
     * Résout l'URL de vue du booking de manière défensive : si la route
     * spécifique n'existe pas, on tombe sur la liste, et en dernier ressort
     * sur '#' (UI-only).
     */
    protected function resolveBookingViewUrl(Booking $booking): string
    {
        /*
         * `Route::has()` PLUTÔT QU'UNE EXCEPTION RATTRAPÉE (2026-08-05).
         *
         * La version précédente visait une route de détail « future-proof, si tu ajoutes la route
         * un jour ». Cette route n'existant pas, l'appel levait à CHAQUE passage pour être aussitôt
         * rattrapé : une exception par réservation créée, en guise de branchement.
         *
         * Elle n'est plus nommée du tout. Nommer une route inexistante, même sous un garde, laisse
         * une cible que rien ne vérifie et que les outils d'analyse ne peuvent pas distinguer d'une
         * faute de frappe. Le jour où la page de détail existera, l'ajouter ici en tête.
         */
        if (Route::has('client.rendezvous.index')) {
            return route('client.rendezvous.index', [], false);
        }

        return '#';
    }
}
