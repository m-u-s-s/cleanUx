<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Models\Country;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Services\Admin\SurgeOverviewService;
use App\Services\OrderEngine\CatalogArchiver;
use App\Services\OrderEngine\QuestionInsights;
use App\Services\OrderEngine\QuestionnaireValidator;
use App\Services\OrderEngine\TradeFormPublisher;
use App\Support\Livewire\Concerns\Admin\ManagesCatalogTranslations;
use App\Support\Livewire\Concerns\Admin\ManagesTradeForm;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Le catalogue : secteurs et métiers.
 *
 * @property-read array<int, int> $tradesLosingClients
 */
#[Layout('layouts.app')]
class CatalogCenter extends Component
{
    /** Même seuil que `QuestionInsights::worstOffenders()` : en deçà, aucun verdict. */
    private const MINIMUM_ORDERS_TO_CONCLUDE = 20;

    /** Le refus vaut au niveau du composant, pas seulement de la route. */
    use EnforcesAdminAccess;

    /* La liste des langues, partagée avec `QuestionnaireBuilder` — une seule source. */
    use ManagesCatalogTranslations;

    /**
     * CE QUI SE TRADUIT, ET RIEN D'AUTRE. La liste est FERMÉE, et par type d'objet.
     *
     * @var array<string, list<string>>
     */
    private const CHAMPS_TRADUISIBLES = [
        'sector' => ['name', 'tagline'],
        'trade' => ['name', 'short_description', 'description'],
    ];

    // Le formulaire complet d'un métier — vingt et un champs — partagé avec `/admin/trades`.
    use ManagesTradeForm;

    public ?int $editingSectorId = null;

    /** Le secteur qui recevra le métier en cours de création. */
    public ?int $secteurCible = null;

    public bool $creationMetierOuverte = false;

    /** @var array<string, mixed> */
    public array $sectorForm = [];

    public ?int $archivingSectorId = null;

    /** @var array<string, mixed>|null */
    public ?array $archiveImpact = null;

    public ?string $flash = null;

    /** Les métiers sans secteur n'apparaissent pas dans le carrousel : on ne les cache pas pour autant. */
    public bool $showOrphanTrades = false;

    public Country $country;

    public ServiceZone $zone;

    public function mount(Country $country, ServiceZone $zone): void
    {
        // L'URL porte les deux identifiants, et rien n'empêche d'en écrire un couple incohérent à la main.
        abort_unless($zone->country_id === $country->id, 404);

        $this->country = $country;
        $this->zone = $zone;
        $this->resetSectorForm();
    }

    /** Ouvrir ou fermer un métier DANS CETTE ZONE. L'activation et le prix sont la même ligne. */
    public function basculerMetierDansLaZone(int $tradeId): void
    {
        // Ouvrir un métier dans une zone décidera de son prix et de sa disponibilité : c'est une
        // écriture, même si la ligne créée est modeste.
        if ($this->refusesWrite()) {
            return;
        }

        $ligne = TradeZonePricing::query()->firstOrNew([
            'trade_id' => $tradeId,
            'service_zone_id' => $this->zone->id,
        ]);

        if (! $ligne->exists) {
            // Première ouverture : on part du prix du métier.
            $ligne->base_rate_cents = (int) (Trade::findOrFail($tradeId)->base_price_cents ?? 0);
            // La colonne est un décimal : la remplir avec un float PHP la ferait arrondir au
            // hasard de la conversion. On écrit la chaîne que la base attend.
            $ligne->surge_multiplier = '1.00';
            $ligne->is_active = true;
        } else {
            $ligne->is_active = ! $ligne->is_active;
        }

        $ligne->save();

        unset($this->metiersActifsDansLaZone, $this->metiersEnImmediatDansLaZone);
    }

    /** Ouvrir ou fermer l'INTERVENTION IMMÉDIATE pour ce métier DANS CETTE ZONE. */
    public function basculerImmediatDansLaZone(int $tradeId): void
    {
        if ($this->refusesWrite()) {
            return;
        }

        $ligne = TradeZonePricing::query()
            ->where('trade_id', $tradeId)
            ->where('service_zone_id', $this->zone->id)
            ->first();

        if (! $ligne || ! $ligne->is_active) {
            $this->flash = 'Ouvrez d’abord ce métier dans la zone : l’immédiat n’a pas de sens sur un service qu’on n’y vend pas.';

            return;
        }

        $ligne->update(['asap_enabled' => ! $ligne->asap_enabled]);

        unset($this->metiersEnImmediatDansLaZone);
    }

    /** RÉGLER LA MAJORATION DE CE MÉTIER DANS CETTE ZONE. */
    public function reglerMajorationDansLaZone(int $tradeId, string $multiplicateur): void
    {
        if ($this->refusesWrite()) {
            return;
        }

        $valeur = (float) str_replace(',', '.', trim($multiplicateur));
        $plafond = (float) config('surge.max_multiplier', 3.0);

        if ($valeur < 1.0 || $valeur > $plafond) {
            $this->flash = sprintf(
                'La majoration doit rester entre 1,00 et %s : en dessous ce serait une remise, au-dessus le moteur la ramènerait au plafond.',
                number_format($plafond, 2, ',', ' '),
            );

            return;
        }

        $ligne = TradeZonePricing::query()
            ->where('trade_id', $tradeId)
            ->where('service_zone_id', $this->zone->id)
            ->first();

        if (! $ligne || ! $ligne->is_active) {
            $this->flash = 'Ouvrez d’abord ce métier dans la zone : une majoration sur un service qu’on n’y vend pas ne s’applique à rien.';

            return;
        }

        // La colonne est décimale : on écrit la chaîne formatée plutôt qu'un flottant, dont la
        // conversion arrondirait au hasard.
        $ligne->update(['surge_multiplier' => number_format($valeur, 2, '.', '')]);

        unset($this->majorationsDansLaZone);

        $this->flash = 'Majoration enregistrée.';
    }

    /**
     * La majoration en vigueur pour chaque métier de cette zone.
     *
     * @return array<int, string> identifiant du métier → multiplicateur
     */
    #[Computed]
    public function majorationsDansLaZone(): array
    {
        return TradeZonePricing::query()
            ->where('service_zone_id', $this->zone->id)
            ->pluck('surge_multiplier', 'trade_id')
            ->map(fn ($valeur) => (string) $valeur)
            ->all();
    }

    /**
     * Quels métiers acceptent l'immédiat dans cette zone.
     *
     * @return array<int, bool> identifiant du métier → immédiat ouvert
     */
    #[Computed]
    public function metiersEnImmediatDansLaZone(): array
    {
        return TradeZonePricing::query()
            ->where('service_zone_id', $this->zone->id)
            ->pluck('asap_enabled', 'trade_id')
            ->map(fn ($actif) => (bool) $actif)
            ->all();
    }

    /** Ouvre le formulaire de création, pour un secteur donné. */
    public function ouvrirCreationMetier(int $sectorId): void
    {
        $this->secteurCible = Sector::query()->findOrFail($sectorId)->id;
        $this->resetTradeForm();
        $this->creationMetierOuverte = true;
    }

    public function fermerCreationMetier(): void
    {
        $this->creationMetierOuverte = false;
        $this->secteurCible = null;
        $this->resetTradeForm();
    }

    /** Enregistre le métier, le rattache au secteur, et l'ouvre dans CETTE zone. */
    public function enregistrerMetier(): void
    {
        // Le lecteur seul lit. `EnforcesAdminAccess` s'arrête à « est-ce un administrateur » : un
        // compte en lecture seule le franchit et atteindrait cette écriture.
        if ($this->refusesWrite()) {
            return;
        }

        $trade = $this->persistTradeForm();

        // `null` signifie « refusé » : les erreurs sont posées, et le formulaire doit rester
        // ouvert avec les vingt champs que l'administrateur venait de remplir.
        if ($trade === null) {
            return;
        }

        if ($this->secteurCible !== null) {
            $trade->update(['sector_id' => $this->secteurCible]);
        }

        TradeZonePricing::query()->updateOrCreate(
            ['trade_id' => $trade->id, 'service_zone_id' => $this->zone->id],
            [
                'base_rate_cents' => (int) ($trade->base_price_cents ?? 0),
                'surge_multiplier' => '1.00',
                'is_active' => true,
            ],
        );

        $this->flash = "Métier « {$trade->name} » créé et ouvert dans {$this->zone->name}.";
        $this->fermerCreationMetier();

        unset($this->sectors, $this->orphanTrades, $this->tradeStatuses, $this->metiersActifsDansLaZone);
    }

    /**
     * Quels métiers sont ouverts dans cette zone.
     *
     * @return array<int, bool> identifiant du métier → actif
     */
    #[Computed]
    public function metiersActifsDansLaZone(): array
    {
        return TradeZonePricing::query()
            ->where('service_zone_id', $this->zone->id)
            ->pluck('is_active', 'trade_id')
            ->map(fn ($actif) => (bool) $actif)
            ->all();
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────────────────────

    /** Écrit — ou retire — la traduction d'un libellé de catalogue. */
    public function saveTranslation(string $type, int $id, string $locale, string $field, ?string $value): void
    {
        if ($this->refusesWrite()) {
            return;
        }

        if (! array_key_exists($locale, $this->translationLocales())) {
            return;
        }

        if (! in_array($field, self::CHAMPS_TRADUISIBLES[$type] ?? [], true)) {
            return;
        }

        $modele = match ($type) {
            'sector' => Sector::query()->find($id),
            'trade' => Trade::query()->find($id),
            default => null,
        };

        $modele?->setTranslation($field, $locale, $value);

        // Le cache de `#[Computed]` porte les traductions déjà chargées : sans cette invalidation,
        // l'écran continuerait d'afficher la valeur d'avant la saisie.
        unset($this->sectors);
    }

    /** Les secteurs et leurs métiers, TRADUCTIONS COMPRISES. */
    #[Computed]
    public function sectors()
    {
        return Sector::query()
            ->ordered()
            ->with([
                'translations',
                'trades' => fn ($q) => $q->orderBy('sort_order')->with('translations'),
            ])
            ->get();
    }

    /** Métiers actifs rattachés à aucun secteur. */
    #[Computed]
    public function orphanTrades()
    {
        return Trade::query()
            ->whereNull('sector_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * L'état de publication de chaque métier, en un coup d'œil.
     *
     * @return array<int, array{blocking: int, pending: bool, version: int|null}>
     */
    #[Computed]
    public function tradeStatuses(): array
    {
        $validator = app(QuestionnaireValidator::class);
        $publisher = app(TradeFormPublisher::class);

        // Sorti de la boucle, et lu en PROPRIÉTÉ.
        $losing = $this->tradesLosingClients;
        $statuses = [];

        foreach ($this->sectors()->flatMap->trades as $trade) {
            $blocking = collect($validator->inspect($trade))
                ->where('severity', QuestionnaireValidator::SEVERITY_ERROR)
                ->count();

            $statuses[$trade->id] = [
                'blocking' => $blocking,
                'pending' => $publisher->hasUnpublishedChanges($trade),
                'version' => $publisher->currentRevision($trade)?->version,
                'losing' => in_array($trade->id, $losing, true),
            ];
        }

        return $statuses;
    }

    /**
     * Les métiers dont une question fait décrocher les clients.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function tradesLosingClients(): array
    {
        $volumes = DB::table('order_draft_items')
            ->selectRaw('trade_id, count(*) as total')
            ->groupBy('trade_id')
            ->havingRaw('count(*) >= ?', [self::MINIMUM_ORDERS_TO_CONCLUDE])
            ->pluck('total', 'trade_id');

        if ($volumes->isEmpty()) {
            return [];
        }

        $insights = app(QuestionInsights::class);

        return Trade::query()
            ->whereIn('id', $volumes->keys())
            ->get()
            ->filter(fn (Trade $trade) => $insights->worstOffenders($trade)->isNotEmpty())
            ->pluck('id')
            ->all();
    }

    // ─── Secteurs ────────────────────────────────────────────────────────────────────────────

    public function startNewSector(): void
    {
        $this->resetSectorForm();
        $this->editingSectorId = 0;
    }

    public function editSector(int $sectorId): void
    {
        $sector = Sector::find($sectorId);

        if (! $sector) {
            return;
        }

        $this->editingSectorId = $sector->id;
        $this->sectorForm = [
            'name' => $sector->name,
            'slug' => $sector->slug,
            'tagline' => $sector->tagline,
            'accent_color' => $sector->accent_color,
            'icon' => $sector->icon,
        ];
    }

    public function saveSector(): void
    {
        // Le lecteur seul lit. `EnforcesAdminAccess` s'arrête à « est-ce un administrateur » : un
        // compte en lecture seule le franchit et atteindrait cette écriture.
        if ($this->refusesWrite()) {
            return;
        }

        $data = $this->validate([
            'sectorForm.name' => ['required', 'string', 'max:120'],
            'sectorForm.slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
            'sectorForm.tagline' => ['nullable', 'string', 'max:190'],
            // Une couleur mal saisie casserait le carrousel en silence : on la valide plutôt que
            // de la poser telle quelle dans un attribut de style.
            'sectorForm.accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sectorForm.icon' => ['nullable', 'string', 'max:80'],
        ], [
            'sectorForm.slug.regex' => 'Le slug ne peut contenir que des minuscules, des chiffres et des tirets.',
            'sectorForm.accent_color.regex' => 'La couleur doit être un hexadécimal, par exemple #0E7490.',
        ])['sectorForm'];

        if ($this->editingSectorId) {
            Sector::find($this->editingSectorId)?->update($data);
            $this->flash = 'Secteur mis à jour.';
        } else {
            Sector::create($data + [
                'sort_order' => (int) Sector::max('sort_order') + 1,
                'is_active' => true,
            ]);
            $this->flash = 'Secteur créé. Rattachez-lui des métiers pour qu’il apparaisse.';
        }

        $this->cancelSector();
        $this->refreshDerived();
    }

    public function cancelSector(): void
    {
        $this->editingSectorId = null;
        $this->resetSectorForm();
    }

    public function updatedSectorFormName(string $value): void
    {
        if (! $this->editingSectorId && blank($this->sectorForm['slug'] ?? null)) {
            $this->sectorForm['slug'] = Str::slug($value);
        }
    }

    /** Désactiver retire du carrousel ; archiver range. Deux gestes distincts, à dessein. */
    public function toggleSector(int $sectorId): void
    {
        // Le lecteur seul lit. `EnforcesAdminAccess` s'arrête à « est-ce un administrateur » : un
        // compte en lecture seule le franchit et atteindrait cette écriture.
        if ($this->refusesWrite()) {
            return;
        }

        $sector = Sector::find($sectorId);
        $sector?->update(['is_active' => ! $sector->is_active]);

        $this->refreshDerived();
    }

    public function confirmArchiveSector(int $sectorId): void
    {
        $sector = Sector::find($sectorId);

        if (! $sector) {
            return;
        }

        $this->archivingSectorId = $sectorId;
        $this->archiveImpact = app(CatalogArchiver::class)->impactOf($sector);
    }

    public function archiveSector(): void
    {
        // Le lecteur seul lit. `EnforcesAdminAccess` s'arrête à « est-ce un administrateur » : un
        // compte en lecture seule le franchit et atteindrait cette écriture.
        if ($this->refusesWrite()) {
            return;
        }

        $sector = Sector::find($this->archivingSectorId);

        if ($sector) {
            app(CatalogArchiver::class)->archive($sector);
            $this->flash = 'Secteur archivé. Ses métiers restent intacts.';
        }

        $this->cancelArchive();
        $this->refreshDerived();
    }

    public function cancelArchive(): void
    {
        $this->archivingSectorId = null;
        $this->archiveImpact = null;
    }

    /** Le lecteur seul lit. */
    private function refusesWrite(): bool
    {
        // La règle vit dans la Policy : on la consulte, on ne la redit pas.
        return ! Gate::allows('update', Trade::class);
    }

    /**
     * L'ordre des secteurs, tel que le navigateur l'a composé.
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderSectors(array $orderedIds): void
    {
        if ($this->refusesWrite()) {
            return;
        }

        $own = $this->sectors()->keyBy('id');
        $kept = $this->keepKnown($orderedIds, $own);

        // Une liste partielle laisserait des secteurs sans rang défini, donc à une place
        // arbitraire au prochain affichage : on refuse plutôt que de réordonner à moitié.
        if ($kept === null) {
            return;
        }

        DB::transaction(function () use ($kept, $own) {
            $kept->each(fn (int $id, int $position) => $own[$id]->update(['sort_order' => $position]));
        });

        $this->refreshDerived();
    }

    /**
     * L'ordre des métiers DANS un secteur.
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderTrades(int $sectorId, array $orderedIds): void
    {
        if ($this->refusesWrite()) {
            return;
        }

        $sector = $this->sectors()->firstWhere('id', $sectorId);

        if (! $sector) {
            return;
        }

        // Les métiers d'un AUTRE secteur ne se glissent pas ici : une liste forgée réordonnerait
        // sinon le secteur voisin.
        $own = $sector->trades->keyBy('id');
        $kept = $this->keepKnown($orderedIds, $own);

        if ($kept === null) {
            return;
        }

        DB::transaction(function () use ($kept, $own) {
            $kept->each(fn (int $id, int $position) => $own[$id]->update(['sort_order' => $position]));
        });

        $this->refreshDerived();
    }

    /** Les flèches, qui restent. */
    public function moveTrade(int $tradeId, int $direction): void
    {
        if ($this->refusesWrite()) {
            return;
        }

        $trade = Trade::find($tradeId);
        $sector = $trade?->sector_id ? $this->sectors()->firstWhere('id', $trade->sector_id) : null;

        if (! $sector) {
            return;
        }

        $ordered = $sector->trades->values();
        $index = $ordered->search(fn (Trade $t) => $t->id === $tradeId);
        $target = $index === false ? -1 : $index + $direction;

        if ($index === false || $target < 0 || $target >= $ordered->count()) {
            return;
        }

        DB::transaction(function () use ($ordered, $index, $target) {
            $ordered->each(fn (Trade $t, int $i) => $t->update(['sort_order' => $i]));
            $ordered[$index]->update(['sort_order' => $target]);
            $ordered[$target]->update(['sort_order' => $index]);
        });

        $this->refreshDerived();
    }

    /**
     * La liste reçue décrit-elle EXACTEMENT ce qu'on possède ?
     *
     * @param  array<int, int|string>  $orderedIds
     * @param  Collection<int, mixed>  $own
     * @return Collection<int, int>|null
     */
    private function keepKnown(array $orderedIds, $own)
    {
        $kept = collect($orderedIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $own->has($id))
            ->values();

        return ($kept->count() === $own->count() && $kept->count() === count($orderedIds))
            ? $kept
            : null;
    }

    public function moveSector(int $sectorId, int $direction): void
    {
        if ($this->refusesWrite()) {
            return;
        }

        $ordered = $this->sectors()->values();
        $index = $ordered->search(fn ($s) => $s->id === $sectorId);

        if ($index === false) {
            return;
        }

        $target = $index + $direction;

        if ($target < 0 || $target >= $ordered->count()) {
            return;
        }

        DB::transaction(function () use ($ordered, $index, $target) {
            $ordered->each(fn ($s, $i) => $s->update(['sort_order' => $i]));
            $ordered[$index]->update(['sort_order' => $target]);
            $ordered[$target]->update(['sort_order' => $index]);
        });

        $this->refreshDerived();
    }

    // ─── Métiers ─────────────────────────────────────────────────────────────────────────────

    /** Rattacher un métier orphelin à un secteur : c'est ce qui le fait entrer dans le parcours. */
    public function attachTrade(int $tradeId, int $sectorId): void
    {
        // Le lecteur seul lit. `EnforcesAdminAccess` s'arrête à « est-ce un administrateur » : un
        // compte en lecture seule le franchit et atteindrait cette écriture.
        if ($this->refusesWrite()) {
            return;
        }

        $trade = Trade::find($tradeId);
        $sector = Sector::find($sectorId);

        if (! $trade || ! $sector) {
            return;
        }

        $trade->update([
            'sector_id' => $sector->id,
            'sort_order' => (int) $sector->trades()->max('sort_order') + 1,
        ]);

        $this->flash = sprintf('« %s » rattaché à « %s ».', $trade->name, $sector->name);
        $this->refreshDerived();
    }

    public function toggleTrade(int $tradeId): void
    {
        // Le lecteur seul lit. `EnforcesAdminAccess` s'arrête à « est-ce un administrateur » : un
        // compte en lecture seule le franchit et atteindrait cette écriture.
        if ($this->refusesWrite()) {
            return;
        }

        $trade = Trade::find($tradeId);
        $trade?->update(['is_active' => ! $trade->is_active]);

        $this->refreshDerived();
    }

    // ─── Interne ─────────────────────────────────────────────────────────────────────────────

    protected function resetSectorForm(): void
    {
        $this->sectorForm = [
            'name' => '',
            'slug' => '',
            'tagline' => null,
            'accent_color' => null,
            'icon' => null,
        ];
    }

    protected function refreshDerived(): void
    {
        unset($this->sectors, $this->orphanTrades, $this->tradeStatuses);
    }

    public function render()
    {
        return view('livewire.admin.order-engine.catalog-center', [
            // LA CARTE DES MAJORATIONS (E28).
            'carteDesMajorations' => app(SurgeOverviewService::class)->carte(),
        ]);
    }
}
