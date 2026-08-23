<?php

namespace App\Services\Quotes;

use App\Models\Booking;
use App\Models\ProviderQuote;
use App\Models\ProviderQuoteLine;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\Organizations\OrganizationNotifier;
use App\Services\Pricing\TradePricingEngine;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** LE DEVIS QUE LA SOCIÉTÉ BÂTIT ELLE-MÊME (E24). CE QUI EXISTAIT NE COUVRAIT PAS CE CAS. */
class ProviderQuoteService
{
    public function __construct(
        protected TradePricingEngine $pricing,
        protected OrganizationNotifier $notifier,
    ) {}

    /** Ouvrir un brouillon. */
    public function ouvrirUnBrouillon(
        int $organisationId,
        User $auteur,
        string $titre,
        ?int $clientUserId = null,
        ?int $clientOrganizationId = null,
    ): ProviderQuote {
        return ProviderQuote::query()->create([
            'organization_account_id' => $organisationId,
            'client_user_id' => $clientUserId,
            'client_organization_id' => $clientOrganizationId,
            'reference' => ProviderQuote::genererUneReference(),
            'title' => $titre,
            'status' => ProviderQuote::STATUS_DRAFT,
            'created_by_user_id' => $auteur->id,
            'valid_until' => Carbon::now()->addDays(30),
        ]);
    }

    /**
     * Ajouter une ligne. `$prixUnitaireCents` à `null` retient la SUGGESTION du moteur.
     *
     * @throws DomainException
     */
    public function ajouterUneLigne(
        ProviderQuote $devis,
        int $tradeId,
        string $libelle,
        float $quantite = 1,
        ?int $prixUnitaireCents = null,
        ?int $serviceCatalogId = null,
        ?string $unite = null,
    ): ProviderQuoteLine {
        $this->exigerUnBrouillon($devis);

        if ($quantite <= 0) {
            throw new DomainException('Une ligne de devis porte une quantité positive.');
        }

        $suggestion = $this->suggestionPour($serviceCatalogId, $quantite);

        $prixRetenu = $prixUnitaireCents ?? $suggestion ?? 0;

        $ligne = ProviderQuoteLine::query()->create([
            'provider_quote_id' => $devis->id,
            'trade_id' => $tradeId,
            'service_catalog_id' => $serviceCatalogId,
            'label' => $libelle,
            'quantity' => $quantite,
            'unit' => $unite ?? 'unité',
            'unit_price_cents' => $prixRetenu,
            'total_cents' => (int) round($prixRetenu * $quantite),
            // Ce que le moteur proposait : l'écart avec le prix retenu rend la remise lisible.
            'suggested_price_cents' => $suggestion,
            'sort_order' => (int) $devis->lines()->count(),
        ]);

        $this->recalculer($devis);

        return $ligne;
    }

    /** @throws DomainException */
    public function retirerUneLigne(ProviderQuote $devis, int $ligneId): void
    {
        $this->exigerUnBrouillon($devis);

        // Scopé sur le devis : un identifiant forgé ne doit pas retirer la ligne d'un autre devis.
        $devis->lines()->whereKey($ligneId)->delete();

        $this->recalculer($devis);
    }

    /**
     * Envoyer au client.
     *
     * @throws DomainException
     */
    public function envoyer(ProviderQuote $devis): ProviderQuote
    {
        $this->exigerUnBrouillon($devis);

        if ($devis->lines()->count() === 0) {
            // Un devis vide n'est pas une proposition : le client recevrait une invitation à
            // rappeler, ce qu'un devis est censé éviter.
            throw new DomainException('Un devis sans ligne n’a rien à proposer.');
        }

        if ($devis->client_user_id === null && $devis->client_organization_id === null) {
            throw new DomainException('Indiquez à qui ce devis s’adresse.');
        }

        $this->recalculer($devis);

        $devis->forceFill([
            'status' => ProviderQuote::STATUS_SENT,
            'sent_at' => now(),
        ])->save();

        try {
            $this->notifier->notifierUtilisateur(
                $devis->client_user_id,
                'Nouveau devis : '.$devis->title,
                sprintf('%s — %s €', $devis->reference, number_format($devis->total_cents / 100, 2, ',', ' ')),
                ['provider_quote_id' => $devis->id],
                'provider_quote:sent:'.$devis->id,
            );
        } catch (\Throwable $e) {
            // Le devis est envoyé : une notification qui échoue ne doit pas le remettre en
            // brouillon, sinon le client verrait un document disparaître.
            report($e);
        }

        return $devis->fresh();
    }

    /**
     * Le client accepte — et le travail est créé.
     *
     * @throws DomainException
     */
    public function accepter(ProviderQuote $devis, User $client): ProviderQuote
    {
        if (! $devis->estOuvert()) {
            // L'ÉCHÉANCE COMPTE MÊME SI LE BALAYAGE N'EST PAS PASSÉ.
            throw new DomainException('Ce devis n’est plus valable.');
        }

        if ($devis->client_user_id !== null && (int) $devis->client_user_id !== (int) $client->id) {
            throw new DomainException('Ce devis ne vous est pas adressé.');
        }

        return DB::transaction(function () use ($devis, $client) {
            $devis->forceFill([
                'status' => ProviderQuote::STATUS_ACCEPTED,
                'decided_at' => now(),
            ])->save();

            foreach ($devis->lines()->orderBy('sort_order')->get() as $ligne) {
                try {
                    $booking = Booking::query()->create([
                        'client_id' => $devis->client_user_id ?? $client->id,
                        'trade_id' => $ligne->trade_id,
                        'service_catalog_id' => $ligne->service_catalog_id,
                        'assigned_provider_organization_id' => $devis->organization_account_id,
                        'devis_estime' => $ligne->total_cents / 100,
                        'status' => 'en_attente',
                        'commentaire_client' => $ligne->description ?? $ligne->label,
                        'matching_snapshot' => [
                            'source' => 'provider_quote',
                            'provider_quote_id' => $devis->id,
                            'reference' => $devis->reference,
                            'line_id' => $ligne->id,
                        ],
                    ]);

                    $ligne->forceFill(['booking_id' => $booking->id])->save();
                } catch (\Throwable $e) {
                    // UNE LIGNE QUI ÉCHOUE N'ANNULE PAS LES AUTRES, et l'échec se voit : la ligne reste sans `booking_id`.
                    Log::warning('[provider_quote] création de mission impossible', [
                        'quote' => $devis->reference,
                        'line_id' => $ligne->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->notifierLaSociete($devis, 'Devis accepté : '.$devis->title, $devis->reference);

            return $devis->fresh('lines');
        });
    }

    /** @throws DomainException */
    public function refuser(ProviderQuote $devis, User $client, ?string $motif = null): ProviderQuote
    {
        if ($devis->status !== ProviderQuote::STATUS_SENT) {
            throw new DomainException('Ce devis a déjà été traité.');
        }

        if ($devis->client_user_id !== null && (int) $devis->client_user_id !== (int) $client->id) {
            throw new DomainException('Ce devis ne vous est pas adressé.');
        }

        $devis->forceFill([
            // Conservé, jamais effacé : un refus qu'on efface, c'est une négociation qui recommence
            // de zéro trois mois plus tard.
            'status' => ProviderQuote::STATUS_DECLINED,
            'decided_at' => now(),
            'decision_note' => $motif,
        ])->save();

        $this->notifierLaSociete($devis, 'Devis refusé : '.$devis->title, $motif ?? $devis->reference);

        return $devis->fresh();
    }

    /** Marquer périmés les devis dont l'échéance est passée. */
    public function perimerLesDevisEchus(): int
    {
        return ProviderQuote::query()
            ->where('status', ProviderQuote::STATUS_SENT)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', now()->toDateString())
            ->update(['status' => ProviderQuote::STATUS_EXPIRED]);
    }

    /** Le total, recalculé depuis les lignes — jamais saisi. */
    public function recalculer(ProviderQuote $devis): ProviderQuote
    {
        $devis->forceFill([
            'total_cents' => (int) $devis->lines()->sum('total_cents'),
        ])->save();

        return $devis;
    }

    /** Ce que le moteur de prix propose pour cette prestation. */
    protected function suggestionPour(?int $serviceCatalogId, float $quantite): ?int
    {
        if ($serviceCatalogId === null) {
            return null;
        }

        $service = ServiceCatalog::query()->with('trade')->find($serviceCatalogId);

        if ($service === null) {
            return null;
        }

        try {
            $estimation = $this->pricing->estimate($service, ['quantity' => $quantite]);

            $unitaire = (float) $estimation['unit_price'];

            return $unitaire > 0 ? (int) round($unitaire * 100) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** @throws DomainException */
    protected function exigerUnBrouillon(ProviderQuote $devis): void
    {
        if ($devis->status !== ProviderQuote::STATUS_DRAFT) {
            // Modifier après envoi ferait diverger ce que le client a reçu de ce qu'il accepte.
            throw new DomainException('Un devis envoyé ne se modifie plus.');
        }
    }

    protected function notifierLaSociete(ProviderQuote $devis, string $titre, string $corps): void
    {
        try {
            $this->notifier->notifierPorteursDe(
                organisationId: (int) $devis->organization_account_id,
                permission: 'quotes.manage',
                titre: $titre,
                corps: $corps,
                donnees: ['provider_quote_id' => $devis->id],
                cleIdempotence: 'provider_quote:decided:'.$devis->id,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
