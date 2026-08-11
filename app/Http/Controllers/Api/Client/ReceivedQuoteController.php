<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\ProviderQuote;
use App\Models\ProviderQuoteLine;
use App\Services\Quotes\ProviderQuoteService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LES DEVIS REÇUS, CÔTÉ CLIENT (E24) — sur le téléphone.
 *
 * LA MOITIÉ MANQUANTE DU MODULE. Une société qui bâtit un devis et l'envoie à un client qui ne peut
 * pas y répondre n'a rien envoyé : le client découvre le document par une notification poussée sur
 * son téléphone — et devrait ouvrir un ordinateur pour dire oui.
 *
 * ACCEPTER CRÉE LE TRAVAIL, pas un accusé de réception. Chaque ligne porte un métier et devient une
 * réservation.
 *
 * ET LE SCOPING FAIT PARTIE DE LA REQUÊTE. Un devis porte le nom du client, son adresse et ce qu'il
 * paye : un identifiant forgé ne doit jamais en charger un autre.
 */
class ReceivedQuoteController extends Controller
{
    public function index(): JsonResponse
    {
        $devis = ProviderQuote::query()
            ->where('client_user_id', Auth::id())
            // Un brouillon n'a pas été envoyé : le montrer ferait découvrir un prix que la société
            // n'a pas fini d'écrire.
            ->where('status', '!=', ProviderQuote::STATUS_DRAFT)
            ->with('organizationAccount:id,name')
            ->latest()
            ->get();

        return response()->json([
            'data' => $devis->map(fn (ProviderQuote $document) => [
                'id' => $document->id,
                'reference' => $document->reference,
                'title' => $document->title,
                'provider_name' => $document->organizationAccount?->name,
                'status' => $document->status,
                'total_cents' => $document->total_cents,
                'currency' => $document->currency,
                'valid_until' => $document->valid_until?->toDateString(),
                // L'échéance compte MÊME SI le balayage n'est pas passé : la validité d'un prix ne
                // doit pas dépendre de l'heure du cron.
                'is_open' => $document->estOuvert(),
            ])->all(),
        ]);
    }

    public function show(int $quoteId): JsonResponse
    {
        $devis = $this->devisRecu($quoteId);

        return response()->json([
            'data' => [
                'id' => $devis->id,
                'reference' => $devis->reference,
                'title' => $devis->title,
                'intro' => $devis->intro,
                'provider_name' => $devis->organizationAccount?->name,
                'status' => $devis->status,
                'total_cents' => $devis->total_cents,
                'valid_until' => $devis->valid_until?->toDateString(),
                'is_open' => $devis->estOuvert(),
                'lines' => $devis->lines->map(fn (ProviderQuoteLine $ligne) => [
                    'id' => $ligne->id,
                    'label' => $ligne->label,
                    'trade_name' => $ligne->trade?->name,
                    'quantity' => $ligne->quantity,
                    'unit' => $ligne->unit,
                    'total_cents' => $ligne->total_cents,
                ])->all(),
            ],
        ]);
    }

    public function accept(int $quoteId): JsonResponse
    {
        $devis = $this->devisRecu($quoteId);

        try {
            $devis = app(ProviderQuoteService::class)->accepter($devis, Auth::user());
        } catch (DomainException $e) {
            // « Ce devis n'est plus valable » est une réponse, pas une panne.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'id' => $devis->id,
                'status' => $devis->status,
                // Accepter CRÉE le travail : l'application doit pouvoir dire combien de
                // rendez-vous en sont nés.
                'bookings_created' => $devis->lines->whereNotNull('booking_id')->count(),
            ],
        ]);
    }

    public function decline(Request $request, int $quoteId): JsonResponse
    {
        $donnees = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $devis = $this->devisRecu($quoteId);

        try {
            $devis = app(ProviderQuoteService::class)->refuser(
                $devis,
                Auth::user(),
                $donnees['reason'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $devis->id, 'status' => $devis->status]]);
    }

    /** Un devis QUI M'EST ADRESSÉ, ou 404 — jamais celui d'un autre client. */
    private function devisRecu(int $quoteId): ProviderQuote
    {
        /** @var ProviderQuote $devis */
        $devis = ProviderQuote::query()
            ->where('client_user_id', Auth::id())
            ->where('status', '!=', ProviderQuote::STATUS_DRAFT)
            ->with(['lines.trade:id,name', 'organizationAccount:id,name'])
            ->findOrFail($quoteId);

        return $devis;
    }
}
