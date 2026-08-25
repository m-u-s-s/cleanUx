<?php

namespace App\Livewire\OrderEngine;

use App\Models\ClientPlace;
use App\Models\OrderDraft;
use App\Models\OrderDraftItem;
use App\Models\OrderDraftMedia;
use App\Models\OrganizationSite;
use App\Models\Question;
use App\Models\Sector;
use App\Models\Trade;
use App\Services\Ai\OrderIntentInterpreter;
use App\Services\Client\ClientPlaceService;
use App\Services\Geo\RoutingService;
use App\Services\GeolocationV2\AddressSuggestion;
use App\Services\GeolocationV2\GeocodingService;
use App\Services\OrderEngine\AvailabilitySnapshot;
use App\Services\OrderEngine\BundleComposer;
use App\Services\OrderEngine\ConditionEvaluator;
use App\Services\OrderEngine\HourlyRateResolver;
use App\Services\OrderEngine\OrderDraftManager;
use App\Services\OrderEngine\PriceBreakdown;
use App\Services\OrderEngine\PricingEngine;
use App\Services\OrderEngine\ProviderAvailabilityLookup;
use App\Services\OrderEngine\ProviderShortlist;
use App\Services\OrderEngine\SlotFinder;
use App\Services\OrderEngine\ZonePricingResolver;
use App\Support\Domain\LocationRole;
use App\Support\Domain\OrderMode;
use App\Support\Domain\TradeRouteRules;
use App\Support\Validation\ImagesTeleversees;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Le parcours de commande : secteur, puis métier, puis questions — sans changer de page. */
/**
 * Les valeurs calculées, accessibles en PROPRIÉTÉ.
 *
 * @property-read Collection<int, Sector> $sectors
 * @property-read Collection<int, Trade> $trades
 * @property-read Trade|null $trade
 * @property-read Collection<int, Question> $questions
 * @property-read Collection<int, Question> $visibleQuestions
 * @property-read list<string> $availableModes
 * @property-read PriceBreakdown|null $quote
 * @property-read array<string, mixed>|null $lastChange
 * @property-read AvailabilitySnapshot|null $availability
 * @property-read array<int, Carbon> $dayOptions
 * @property-read list<array<string, mixed>> $slots
 * @property-read Collection<int, array{id: int, name: string, rating: float|null, rating_count: int, missions_count: int, distance_m: int, distance_km: float}> $providerOptions
 * @property-read bool $readyToConfirm
 * @property-read Collection<int, array<string, mixed>> $timeline
 * @property-read Collection<int, array<string, mixed>> $bundleSuggestions
 * @property-read array<string, mixed>|null $bundleQuote
 * @property-read Collection<int, Question> $allVisibleQuestions
 * @property-read Collection<int, Collection<int, Question>> $steps
 * @property-read bool $estUnTrajet
 * @property-read array{distance_km: float, duration_min: int|null, source: string|null, approximatif: bool}|null $route
 * @property-read list<array{lat: float, lng: float}> $pointsDeLaRoute
 */
#[Layout('layouts.app')]
class OrderJourney extends Component
{
    use WithFileUploads;

    /**
     * Le jeton qui rattache un visiteur à son panier, sans compte. */
    public string $sessionToken = '';

    public ?int $sectorId = null;

    public ?int $tradeId = null;

    /**
     * Réponses en cours, indexées par code de question.
     *
     * @var array<string, mixed>
     */
    public array $answers = [];

    /** LES HEURES ACHETÉES, sur un métier facturé au temps passé. */
    public ?float $heuresChoisies = null;

    public string $mode = OrderMode::SCHEDULED;

    /** L'adresse vit au niveau de la COMMANDE, pas de la ligne. */
    public string $address = '';

    public ?float $lat = null;

    public ?float $lng = null;

    /**
     * Le géocodage a échoué : on le dit, plutôt que de laisser un champ muet. */
    public bool $addressUnresolved = false;

    /** La géographie résolue depuis l'adresse — code postal, puis ZONE. */
    public ?string $postalCode = null;

    public ?int $serviceZoneId = null;

    /** LE BÉNÉFICIAIRE (E1) — le client paye, quelqu'un d'autre reçoit. */
    public string $beneficiaryName = '';

    public string $beneficiaryPhone = '';

    public string $beneficiaryNote = '';

    /** Le lieu du carnet retenu (E2). */
    #[Locked]
    public ?int $clientPlaceId = null;

    /**
     * Le refus de réordonnancement, AFFICHÉ : corriger en silence tromperait le client. */
    public string $sequenceError = '';

    /**
     * Jour retenu pour l'intervention, au format ISO. */
    public ?string $selectedDate = null;

    /**
     * Heure de début du créneau retenu, au format H:i. */
    public ?string $selectedSlot = null;

    /** Prestataire choisi, ou `null` pour l'attribution automatique. */
    public ?int $selectedProviderId = null;

    /**
     * Photos en attente d'être jointes.
     *
     * @var array<int, mixed>
     */
    public array $photos = [];

    /** Mode demandé par l'URL, en attente d'un métier. */
    public ?string $intendedMode = null;

    /** Ce qu'on doit au client quand son intention n'a pas pu être honorée. */
    public string $modeNotice = '';

    /** L'étape du questionnaire en cours d'affichage. */
    public int $stepIndex = 0;

    public function mount(?string $sector = null, ?string $trade = null): void
    {
        // Une valeur inventée dans l'URL est ignorée, sans rien casser : la barre d'adresse est une entrée comme une autre, et rien de ce qui en vient n'est cru sur parole.
        $requested = (string) request()->query('mode', '');

        if (in_array($requested, OrderMode::all(), true)) {
            $this->intendedMode = $requested;
        }

        // Le jeton vit dans la SESSION, pas dans une propriété exposée : une propriété Livewire voyage par le navigateur, et le panier de quelqu'un d'autre ne doit pas s'ouvrir en changeant une valeur dans les outils de développement.
        $this->sessionToken = session()->get('order_draft_token') ?: Str::random(48);
        session()->put('order_draft_token', $this->sessionToken);

        // Le panier retrouvé porte peut-être déjà sa géographie : la relire évite de repartir sans zone — donc sans grille de prix locale et sans savoir si l'immédiat est ouvert ici — alors que le client avait déjà saisi son adresse hier.
        $draft = $this->draft();
        $this->address = (string) ($draft->address ?? '');
        $this->lat = $draft->lat !== null ? (float) $draft->lat : null;
        $this->lng = $draft->lng !== null ? (float) $draft->lng : null;
        $this->postalCode = $draft->postal_code;
        $this->serviceZoneId = $draft->service_zone_id !== null ? (int) $draft->service_zone_id : null;

        $this->beneficiaryName = (string) ($draft->beneficiary_name ?? '');
        $this->beneficiaryPhone = (string) ($draft->beneficiary_phone ?? '');
        $this->beneficiaryNote = (string) ($draft->beneficiary_note ?? '');
        $this->clientPlaceId = $draft->client_place_id !== null ? (int) $draft->client_place_id : null;

        $this->rattacherAuLocalDeLaSociete();
        // Le carnet ne pré-remplit QUE si rien n'a encore été saisi : écraser une adresse déjà
        // tapée ferait recommencer le client sans qu'il comprenne pourquoi.
        $this->preremplirDepuisLeCarnet();

        if ($sector) {
            $this->sectorId = Sector::where('slug', $sector)->value('id');
        }

        if ($trade) {
            $this->selectTrade((int) Trade::where('slug', $trade)->value('id'));
        }
    }

    /** COMMANDER POUR UN LOCAL DE SA SOCIÉTÉ — le même parcours, situé d'avance. */
    protected function rattacherAuLocalDeLaSociete(): void
    {
        $siteId = (int) request()->query('site', 0);
        $orgId = Auth::check() ? (int) (Auth::user()->current_organization_id ?? 0) : 0;

        if ($siteId <= 0 || $orgId <= 0) {
            return;
        }

        $site = OrganizationSite::query()
            ->where('organization_account_id', $orgId)
            ->find($siteId);

        if (! $site) {
            return;
        }

        $draft = $this->draft();

        $draft->forceFill([
            'address' => $site->address ?: $draft->address,
            'lat' => $site->lat ?? $draft->lat,
            'lng' => $site->lng ?? $draft->lng,
            'postal_code' => $site->postal_code ?: $draft->postal_code,
            'service_zone_id' => $site->service_zone_id ?? $draft->service_zone_id,
            // Le contexte société vit dans `metadata` : `order_drafts` n'a pas de colonne pour lui, et en ajouter une pour un rattachement qui se revérifie de toute façon à la confirmation coûterait une migration sans rien garantir de plus.
            'metadata' => array_merge((array) $draft->metadata, [
                'organization_account_id' => $orgId,
                'organization_site_id' => $site->id,
            ]),
        ])->save();

        $this->address = (string) ($draft->address ?? '');
        $this->lat = $draft->lat !== null ? (float) $draft->lat : null;
        $this->lng = $draft->lng !== null ? (float) $draft->lng : null;
        $this->postalCode = $draft->postal_code;
        $this->serviceZoneId = $draft->service_zone_id !== null ? (int) $draft->service_zone_id : null;
    }

    /** PRÉ-REMPLIR DEPUIS LE CARNET DE LIEUX (E2). SEULEMENT SI RIEN N'A ENCORE ÉTÉ SAISI. */
    protected function preremplirDepuisLeCarnet(): void
    {
        if (! Auth::check() || $this->address !== '') {
            return;
        }

        $lieu = app(ClientPlaceService::class)->parDefaut(Auth::user());

        if ($lieu === null) {
            return;
        }

        $this->appliquerLeLieu($lieu);
    }

    /** Choisir un lieu du carnet en cours de parcours. */
    public function choisirLeLieu(int $lieuId): void
    {
        if (! Auth::check()) {
            return;
        }

        $lieu = app(ClientPlaceService::class)->lieuDuClient(Auth::user(), $lieuId);

        if ($lieu === null) {
            return;
        }

        $this->appliquerLeLieu($lieu);
    }

    /** @return Collection<int, ClientPlace> */
    #[Computed]
    public function savedPlaces(): Collection
    {
        if (! Auth::check()) {
            return collect();
        }

        return app(ClientPlaceService::class)->pour(Auth::user());
    }

    /** UN LIEU ENREGISTRÉ SANS COORDONNÉES EST UN CUL-DE-SAC — on le géocode au lieu de le subir. */
    protected function completerLesCoordonnees(ClientPlace $lieu): ClientPlace
    {
        if ($lieu->lat !== null && $lieu->lng !== null) {
            return $lieu;
        }

        $texte = trim($lieu->address.' '.($lieu->postal_code ?? ''));

        if ($texte === '') {
            return $lieu;
        }

        try {
            $resultat = app(GeocodingService::class)->geocode(
                $texte,
                (string) Config::get('order_engine.geocoding_country', 'BE'),
            );

            if ($resultat === null) {
                return $lieu;
            }

            $lieu->forceFill([
                'lat' => $resultat->latitude,
                'lng' => $resultat->longitude,
                'postal_code' => $lieu->postal_code ?: $resultat->postalCode,
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('[order_engine] lieu enregistré non géocodable', [
                'client_place_id' => $lieu->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $lieu->fresh() ?? $lieu;
    }

    protected function appliquerLeLieu(ClientPlace $lieu): void
    {
        $lieu = $this->completerLesCoordonnees($lieu);
        $draft = $this->draft();

        $draft->forceFill([
            'address' => $lieu->address,
            'lat' => $lieu->lat ?? $draft->lat,
            'lng' => $lieu->lng ?? $draft->lng,
            'postal_code' => $lieu->postal_code ?: $draft->postal_code,
            'service_zone_id' => $lieu->service_zone_id ?? $draft->service_zone_id,
            'client_place_id' => $lieu->id,
        ])->save();

        $this->address = $lieu->address;
        $this->lat = $lieu->lat;
        $this->lng = $lieu->lng;
        $this->postalCode = $lieu->postal_code;
        $this->serviceZoneId = $lieu->service_zone_id;
        $this->clientPlaceId = $lieu->id;

        // La zone a pu changer : le mode immédiat n'est pas ouvert partout.
        unset($this->availableModes);
    }

    /** RÉSERVER POUR UN PROCHE (E1) — enregistré sur le panier, et reporté à la réservation. */
    public function enregistrerLeBeneficiaire(): void
    {
        $this->validate([
            'beneficiaryName' => ['nullable', 'string', 'max:120'],
            'beneficiaryPhone' => ['nullable', 'string', 'max:40'],
            'beneficiaryNote' => ['nullable', 'string', 'max:500'],
        ]);

        $this->draft()->update([
            'beneficiary_name' => $this->beneficiaryName !== '' ? $this->beneficiaryName : null,
            'beneficiary_phone' => $this->beneficiaryPhone !== '' ? $this->beneficiaryPhone : null,
            'beneficiary_note' => $this->beneficiaryNote !== '' ? $this->beneficiaryNote : null,
        ]);
    }

    /** L'ASSISTANT DE COMMANDE (E5) — décrire son besoin plutôt que de choisir un secteur. */
    public string $besoinDecrit = '';

    /**
     * Ce que l'assistant a compris — affiché avec sa confiance, jamais appliqué en silence.
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $interpretation = null;

    public function interpreterMonBesoin(): void
    {
        if (! feature('ai_order_assistant')) {
            return;
        }

        $this->validate(['besoinDecrit' => ['required', 'string', 'max:1000']]);

        $resultat = app(OrderIntentInterpreter::class)->interpreter($this->besoinDecrit);

        $this->interpretation = $resultat;

        // ON N'APPLIQUE QUE CE DONT ON EST SÛR.
        if ($resultat['trade_id'] !== null && $resultat['confidence'] !== 'low') {
            $this->selectTrade((int) $resultat['trade_id']);
        }
    }

    /** Retenir la proposition qu'on n'avait pas appliquée d'office. */
    public function accepterLaProposition(): void
    {
        $tradeId = $this->interpretation['trade_id'] ?? null;

        if ($tradeId !== null) {
            $this->selectTrade((int) $tradeId);
        }
    }

    // ─── Catalogue ───────────────────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, Sector> */
    #[Computed]
    public function sectors()
    {
        $sectors = Sector::query()
            ->active()
            ->ordered()
            // Les traductions viennent AVEC, comme celles des questions plus bas (ligne 655).
            ->with('translations')
            ->withCount(['trades' => fn ($q) => $q->where('is_active', true)
                ->servableEnMode($this->intendedMode, $this->serviceZoneId)])
            // UN SECTEUR SANS AUCUN MÉTIER SERVABLE N'EST PAS PROPOSÉ.
            ->whereHas('trades', fn ($q) => $q->where('is_active', true)
                ->servableEnMode($this->intendedMode, $this->serviceZoneId))
            ->get();

        // Le signal vivant des cartes.
        $counts = $this->activeProvidersPerSector();

        return $sectors->each(function (Sector $sector) use ($counts) {
            $sector->setAttribute('active_providers_count', (int) ($counts[$sector->id] ?? 0));
        });
    }

    /**
     * Combien de professionnels actifs exercent dans chaque secteur.
     *
     * @return Collection<int, int>
     */
    protected function activeProvidersPerSector(): Collection
    {
        return DB::table('trade_user')
            ->join('trades', 'trades.id', '=', 'trade_user.trade_id')
            ->join('users', 'users.id', '=', 'trade_user.user_id')
            ->join('provider_profiles', 'provider_profiles.user_id', '=', 'users.id')
            ->whereNotNull('trades.sector_id')
            ->where('trades.is_active', true)
            ->where('provider_profiles.status', 'active')
            ->whereIn('users.role', ['provider', 'employe'])
            ->groupBy('trades.sector_id')
            ->selectRaw('trades.sector_id, count(distinct users.id) as total')
            ->pluck('total', 'sector_id');
    }

    /**
     * Les métiers du secteur retenu — ceux du dock.
     *
     * @return Collection<int, Trade>
     */
    #[Computed]
    public function trades(): Collection
    {
        if (! $this->sectorId) {
            return collect();
        }

        return Trade::query()
            ->where('sector_id', $this->sectorId)
            ->where('is_active', true)
            ->servableEnMode($this->intendedMode, $this->serviceZoneId)
            ->orderBy('sort_order')
            ->with('translations')
            ->get();
    }

    #[Computed]
    public function trade(): ?Trade
    {
        return $this->tradeId ? Trade::query()->with('translations')->find($this->tradeId) : null;
    }

    /**
     * Le secteur choisi — le parcours replie son etape et n'en garde que le nom.
     *
     * Servi depuis `sectors()`, deja charge avec ses traductions : un `find()` de plus
     * coutait UNE requete par rendu et faisait sauter le budget du parcours (45).
     * Le repli n'existe que pour le cas ou le secteur choisi sort du filtre courant.
     */
    #[Computed]
    public function sector(): ?Sector
    {
        if (! $this->sectorId) {
            return null;
        }

        return $this->sectors->firstWhere('id', $this->sectorId)
            ?? Sector::query()->with('translations')->find($this->sectorId);
    }

    /**
     * Les questions à poser, dans l'ordre.
     *
     * @return Collection<int, Question>
     */
    #[Computed]
    public function questions(): Collection
    {
        $trade = $this->trade;

        if (! $trade) {
            return collect();
        }

        $query = $trade->questions()->with(['options.translations', 'conditions', 'translations', 'step'])->where('is_active', true);

        if ($this->mode === OrderMode::ASAP) {
            $query->where('is_essential', true);
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * Celles réellement affichées : une condition non remplie masque sa question.
     *
     * @return Collection<int, Question>
     */
    #[Computed]
    public function visibleQuestions(): Collection
    {
        $steps = $this->steps;

        if ($steps->isEmpty()) {
            return collect();
        }

        // L'index peut désigner une étape qui vient de disparaître : une réponse a masqué les
        // questions qui la composaient. On retombe sur la dernière plutôt que sur du vide.
        return $steps[min($this->stepIndex, $steps->count() - 1)];
    }

    /**
     * TOUTES les questions visibles, étapes confondues.
     *
     * @return Collection<int, Question>
     */
    #[Computed]
    public function allVisibleQuestions(): Collection
    {
        return app(ConditionEvaluator::class)->visible($this->questions, $this->answers);
    }

    /**
     * Le questionnaire découpé en étapes RÉELLEMENT visibles. Deux règles, dans cet ordre. 1.
     *
     * @return Collection<int, Collection<int, Question>>
     */
    #[Computed]
    public function steps(): Collection
    {
        $visible = $this->allVisibleQuestions;

        if ($visible->isEmpty()) {
            return collect();
        }

        $declared = $visible->groupBy('step_id');

        // L'administrateur a-t-il vraiment découpé ?
        $hasRealSteps = $declared->keys()->filter(fn ($id) => $id !== '')->count() > 0
            && $declared->count() > 1;

        if ($hasRealSteps) {
            return $declared
                /**
                 * LE `?->` EST NÉCESSAIRE, malgré ce qu'en dit l'analyse statique.
                 *
                 * @phpstan-ignore nullsafe.neverNull
                 */
                ->sortBy(fn (Collection $group) => $group->first()->step?->sort_order ?? -1)
                ->values()
                ->map(fn (Collection $group) => $group->values());
        }

        $perStep = max(1, (int) Config::get('order_engine.max_questions_per_step', 7));

        return $visible->values()->chunk($perStep)->values();
    }

    /** Combien d'étapes le client va réellement traverser. */
    public function stepCount(): int
    {
        return max(1, $this->steps->count());
    }

    /** Le titre écrit par l'administrateur pour l'étape courante, s'il y en a un. */
    public function currentStepTitle(): ?string
    {
        return $this->visibleQuestions()->first()?->step?->title;
    }

    public function nextStep(): void
    {
        if ($this->stepIndex < $this->stepCount() - 1) {
            $this->stepIndex++;
        }
    }

    public function previousStep(): void
    {
        // Revenir ne perd rien : les réponses vivent dans le panier, en base, pas dans l'écran.
        if ($this->stepIndex > 0) {
            $this->stepIndex--;
        }
    }

    /**
     * Les modes que ce métier autorise. Un ravalement de façade n'est pas un service immédiat.
     *
     * @return list<string>
     */
    #[Computed]
    public function availableModes(): array
    {
        $trade = $this->trade;

        if (! $trade) {
            return [OrderMode::SCHEDULED];
        }

        $resolver = app(ZonePricingResolver::class);

        return collect(OrderMode::all())
            ->filter(function (string $mode) use ($trade, $resolver) {
                if (! $trade->allowsMode($mode)) {
                    return false;
                }

                // DEUX VERROUS POUR L'IMMÉDIAT, et ils ne disent pas la même chose.
                if ($mode === OrderMode::ASAP && $this->serviceZoneId !== null) {
                    return $resolver->allowsImmediate($trade, $this->serviceZoneId);
                }

                return true;
            })
            ->values()
            ->all();
    }

    // ─── Prix ────────────────────────────────────────────────────────────────────────────────

    /** L'estimation en direct, recalculée à chaque réponse. */
    #[Computed]
    public function quote(): ?PriceBreakdown
    {
        $trade = $this->trade;

        if (! $trade) {
            return null;
        }

        // Le prix de la ZONE, quand elle est connue. Sans ce contexte, la grille locale existait en
        // base et n'atteignait jamais le calcul : le client de Bruxelles payait le tarif de base.
        $context = ['mode' => $this->mode, 'purchased_minutes' => $this->heuresEnMinutes()]
            + app(ZonePricingResolver::class)->pricingContext((int) $trade->id, $this->serviceZoneId, $this->draft());

        return app(PricingEngine::class)->quoteItem($trade, $this->questions, $this->answers, $context);
    }

    /**
     * Ce que la dernière réponse a changé — « +45 € — plafonds inclus ».
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function lastChange(): ?array
    {
        $quote = $this->quote;

        if (! $quote || ! count($quote->lines)) {
            return null;
        }

        return collect($quote->lines)->last();
    }

    // ─── Adresse et disponibilité ────────────────────────────────────────────────────────────

    /** L'adresse débloque la preuve de disponibilité. */
    public function updatedAddress(): void
    {
        $this->addressUnresolved = false;
        $this->lat = null;
        $this->lng = null;
        $this->postalCode = null;
        $this->serviceZoneId = null;

        $address = trim($this->address);

        if (mb_strlen($address) < 6) {
            $this->refreshDerived();

            return;
        }

        $locality = null;

        try {
            // Le pays qui oriente la recherche est une donnée : ce produit parle six langues et ne
            // vend pas que dans un seul pays. Un code figé ici résout des adresses françaises en
            // Belgique — silencieusement, puisque l'échec du géocodage est volontairement muet.
            $result = app(GeocodingService::class)->geocode(
                $address,
                Config::get('order_engine.geocoding_country', 'BE'),
            );

            if ($result) {
                $this->lat = $result->latitude;
                $this->lng = $result->longitude;
                $this->postalCode = $result->postalCode;
                $locality = $result->locality;
            } else {
                $this->addressUnresolved = true;
            }
        } catch (\Throwable $e) {
            $this->addressUnresolved = true;
            Log::warning('[order_engine] géocodage indisponible', ['error' => $e->getMessage()]);
        }

        // LA ZONE EST RÉSOLUE ICI, pendant le parcours — pas au moment d'envoyer quelqu'un.
        $this->serviceZoneId = app(ZonePricingResolver::class)
            ->resolveZone($this->postalCode, $locality)?->id;

        $this->draft()->update([
            'address' => $address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'postal_code' => $this->postalCode,
            'service_zone_id' => $this->serviceZoneId,
        ]);

        // Le mode retenu peut ne plus être disponible ici : « intervention immédiate » choisie avant l'adresse, sur une zone qui ne l'ouvre pas.
        unset($this->availableModes);

        if (! in_array($this->mode, $this->availableModes, true)) {
            $this->mode = OrderMode::SCHEDULED;
            $this->draft()->update(['mode' => $this->mode]);
            $this->modeNotice = 'L’intervention immédiate n’est pas proposée à cette adresse : nous passons en prise de rendez-vous.';
        }

        $this->refreshDerived();
    }

    // ─── Rattrapage du panier ────────────────────────────────────────────────────────────────

    /** La clé à confier au navigateur, pour retrouver ce panier si le cookie disparaît. */
    #[Computed(persist: false)]
    public function recoveryKey(): ?string
    {
        $manager = app(OrderDraftManager::class);

        return $manager->issueRecoveryKey($this->draft());
    }

    /** Le navigateur présente une clé : on rouvre le panier qu'elle désigne. */
    public function recoverDraft(string $key): void
    {
        $manager = app(OrderDraftManager::class);
        $recovered = $manager->recoverByKey($key);

        if (! $recovered) {
            return;
        }

        // On adopte le jeton du panier retrouvé plutôt que d'y recopier le nôtre : le reste du composant travaille par jeton de session, et la reprise redevient ainsi le chemin ordinaire, sans cas particulier.
        $this->sessionToken = $recovered->session_token;
        session()->put('order_draft_token', $this->sessionToken);

        $this->address = (string) ($recovered->address ?? '');
        $this->lat = $recovered->lat;
        $this->lng = $recovered->lng;
        $this->mode = $recovered->mode;

        $first = $recovered->items()->with('trade')->orderBy('sequence')->first();

        if ($first?->trade) {
            $this->selectTrade($first->trade_id);
        }

        $this->refreshDerived();
    }

    // ─── Chantier : une date par métier ──────────────────────────────────────────────────────

    /** Le client fixe la date d'UN métier du chantier. */
    public function pinItemDate(int $itemId, string $date): void
    {
        $this->sequenceError = '';

        $item = $this->draft()->items()->with('trade')->find($itemId);

        if (! $item || trim($date) === '') {
            return;
        }

        try {
            app(BundleComposer::class)->pinItemDate(
                $this->draft(),
                $item,
                Carbon::parse($date),
            );
        } catch (ValidationException $e) {
            $this->sequenceError = collect($e->errors())->flatten()->implode(' ');
        } catch (\Throwable $e) {
            // Une saisie de date illisible ne fait pas tomber l'écran : on le dit et on continue.
            $this->sequenceError = 'Cette date n’a pas été comprise. Choisissez un jour dans le calendrier.';
        }

        $this->refreshDerived();
    }

    /** Retour à la séquence automatique pour ce métier. */
    public function releaseItemDate(int $itemId): void
    {
        $this->sequenceError = '';
        $item = $this->draft()->items()->find($itemId);

        if ($item) {
            app(BundleComposer::class)->releaseItemDate($this->draft(), $item);
        }

        $this->refreshDerived();
    }

    // ─── Photos ──────────────────────────────────────────────────────────────────────────────

    /** Joint les photos choisies à la ligne de commande du métier courant. */
    public function attachPhotos(): void
    {
        if (! $this->trade || $this->photos === []) {
            return;
        }

        // LA LISTE DES FORMATS N'EST PLUS ÉCRITE ICI.
        $this->validate(
            ['photos.*' => ImagesTeleversees::regles(tailleMaxKo: 8192)],
            [
                'photos.*.mimes' => 'Seules les photos sont acceptées ici (JPEG, PNG, WebP, HEIC).',
                'photos.*.max' => 'Cette photo dépasse 8 Mo. Reprenez-la en qualité normale.',
            ],
        );

        $item = app(OrderDraftManager::class)->itemFor($this->draft(), $this->trade);

        foreach ($this->photos as $photo) {
            $path = $photo->store('order-drafts/'.$this->draft()->reference, 'public');

            OrderDraftMedia::create([
                'order_draft_item_id' => $item->id,
                'uploaded_by_user_id' => Auth::id(),
                'path' => $path,
                'size_bytes' => $photo->getSize(),
                'mime_type' => $photo->getMimeType(),
            ]);
        }

        $this->photos = [];
        unset($this->attachedPhotos);
    }

    /** Le client change d'avis. */
    public function removePhoto(int $mediaId): void
    {
        $media = OrderDraftMedia::query()
            ->whereHas('item', fn ($q) => $q->where('order_draft_id', $this->draft()->id))
            ->find($mediaId);

        if (! $media) {
            return;
        }

        Storage::disk('public')->delete($media->path);
        $media->delete();

        unset($this->attachedPhotos);
    }

    /**
     * Les photos déjà jointes au métier courant.
     *
     * @return Collection<int, OrderDraftMedia>
     */
    #[Computed]
    public function attachedPhotos(): Collection
    {
        $trade = $this->trade;

        if (! $trade) {
            return collect();
        }

        $item = $this->draft()->items()->where('trade_id', $trade->id)->first();

        return $item ? $item->media()->orderBy('id')->get() : collect();
    }

    /**
     * Les adresses proposées pendant la frappe.
     *
     * @return list<AddressSuggestion>
     */
    #[Computed]
    public function addressSuggestions(): array
    {
        $typed = trim($this->address);

        if (mb_strlen($typed) < 3) {
            return [];
        }

        try {
            $suggestions = app(GeocodingService::class)->autocomplete(
                $typed,
                Config::get('order_engine.geocoding_country', 'BE'),
                5,
            );

            // Une seule proposition, identique à ce qui est écrit : le client a déjà choisi.
            return array_values(array_filter(
                $suggestions,
                fn (AddressSuggestion $s) => $s->description !== $typed,
            ));
        } catch (\Throwable $e) {
            // Même règle que le géocodage : un service de suggestions en panne fait perdre un
            // confort, jamais la commande.
            Log::warning('[order_engine] suggestions d’adresse indisponibles', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /** Le client retient une suggestion : elle porte déjà sa position. */
    public function chooseAddressSuggestion(string $description, ?float $lat = null, ?float $lng = null): void
    {
        $this->address = $description;

        if ($lat === null || $lng === null) {
            $this->updatedAddress();

            return;
        }

        $this->addressUnresolved = false;
        $this->lat = $lat;
        $this->lng = $lng;

        $this->draft()->update(['address' => $description, 'lat' => $lat, 'lng' => $lng]);

        // La suggestion porte sa position mais pas forcément son code postal : on redemande la
        // géographie au serveur plutôt que de laisser le panier sans zone — auquel cas le prix
        // retomberait sur le tarif national et le mode immédiat s'afficherait partout.
        $this->resolveGeographyFromCoordinates($lat, $lng);
        $this->refreshDerived();
    }

    /** « Utiliser ma position » — le client a déjà l'information dans sa poche. */
    public function useMyPosition(float $lat, float $lng): void
    {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->addressUnresolved = false;

        try {
            $result = app(GeocodingService::class)->reverseGeocode($lat, $lng);

            if ($result) {
                $this->address = $result->formattedAddress ?? $this->address;
            }
        } catch (\Throwable $e) {
            Log::warning('[order_engine] position non nommable', ['error' => $e->getMessage()]);
        }

        $this->draft()->update([
            'address' => trim($this->address) ?: null,
            'lat' => $lat,
            'lng' => $lng,
        ]);

        $this->resolveGeographyFromCoordinates($lat, $lng);
        $this->refreshDerived();
    }

    /** Le code postal et la zone, depuis une position. */
    protected function resolveGeographyFromCoordinates(float $lat, float $lng): void
    {
        try {
            $result = app(GeocodingService::class)->reverseGeocode($lat, $lng);

            if ($result?->postalCode) {
                $this->postalCode = $result->postalCode;
                $this->serviceZoneId = app(ZonePricingResolver::class)
                    ->resolveZone($result->postalCode, $result->locality)?->id;
            }
        } catch (\Throwable $e) {
            Log::warning('[order_engine] géographie non résolue depuis la position', [
                'error' => $e->getMessage(),
            ]);
        }

        $this->draft()->update([
            'postal_code' => $this->postalCode,
            'service_zone_id' => $this->serviceZoneId,
        ]);

        unset($this->availableModes);

        if (! in_array($this->mode, $this->availableModes, true)) {
            $this->mode = OrderMode::SCHEDULED;
            $this->draft()->update(['mode' => $this->mode]);
            $this->modeNotice = 'L’intervention immédiate n’est pas proposée à cette adresse : nous passons en prise de rendez-vous.';
        }
    }

    /** Ce qu'on peut honnêtement promettre. `null` tant que l'adresse n'est pas située. */
    #[Computed]
    public function availability(): ?AvailabilitySnapshot
    {
        $trade = $this->trade;

        if (! $trade || $this->lat === null || $this->lng === null) {
            return null;
        }

        return app(ProviderAvailabilityLookup::class)->forTrade($trade, $this->lat, $this->lng);
    }

    // ─── Créneaux et attribution ─────────────────────────────────────────────────────────────

    /**
     * Les jours proposés au choix.
     *
     * @return list<Carbon>
     * @return array<int, Carbon>
     */
    #[Computed]
    public function dayOptions(): array
    {
        $days = (int) config('order_engine.slot_days_ahead', 14);

        return collect(range(0, $days))
            ->map(fn (int $offset) => Carbon::today()->addDays($offset))
            ->all();
    }

    /**
     * La grille du jour retenu — créneaux disponibles ET indisponibles.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function slots(): array
    {
        $trade = $this->trade;

        if (! $trade || $this->lat === null || $this->lng === null || ! $this->selectedDate) {
            return [];
        }

        return app(SlotFinder::class)->forDay(
            $trade,
            $this->lat,
            $this->lng,
            Carbon::parse($this->selectedDate),
        );
    }

    /**
     * Les professionnels proposés, pour qui veut choisir. La liste reste facultative.
     *
     * @return Collection<int, array{id: int, name: string, rating: float|null, rating_count: int, missions_count: int, distance_m: int, distance_km: float}>
     */
    #[Computed]
    public function providerOptions(): Collection
    {
        $trade = $this->trade;

        if (! $trade || $this->lat === null || $this->lng === null) {
            return collect();
        }

        return app(ProviderShortlist::class)->forTrade($trade, $this->lat, $this->lng);
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        // Changer de jour invalide le créneau : le garder afficherait une heure retenue sur une
        // journée où elle n'existe peut-être pas.
        $this->selectedSlot = null;
        $this->refreshDerived();
    }

    /** Un créneau indisponible ne se retient pas, même si l'interface a été contournée. */
    public function selectSlot(string $time): void
    {
        $slot = collect($this->slots)->first(
            fn (array $s) => $s['start']->format('H:i') === $time && $s['available'],
        );

        if (! $slot) {
            return;
        }

        $this->selectedSlot = $time;

        $this->draft()->update(['scheduled_at' => $slot['start']]);
        $this->refreshDerived();
    }

    public function selectProvider(?int $providerId): void
    {
        // Un prestataire absent de la liste proposée n'est pas retenu : la valeur vient du
        // navigateur, et le serveur ne lui fait pas confiance.
        if ($providerId !== null && ! $this->providerOptions->contains('id', $providerId)) {
            return;
        }

        $this->selectedProviderId = $providerId;

        // Le choix est ÉCRIT sur la ligne, pas seulement gardé à l'écran.
        if ($trade = $this->trade) {
            app(OrderDraftManager::class)
                ->itemFor($this->draft(), $trade)
                ->update(['provider_id' => $providerId]);
        }

        $this->refreshDerived();
    }

    /** Tout est-il réuni pour confirmer ? Le prestataire, lui, n'est jamais obligatoire. */
    #[Computed]
    public function readyToConfirm(): bool
    {
        return $this->trade !== null
            && $this->lat !== null
            && $this->selectedDate !== null
            && $this->selectedSlot !== null;
    }

    // ─── Multi-services ──────────────────────────────────────────────────────────────────────

    /**
     * Le chantier tel qu'il se déroulera : chaque métier, son rang, et quand il peut commencer.
     *
     * @return Collection<int, array{item: OrderDraftItem, trade: Trade, starts_at: Carbon, ends_at: Carbon, waits_for: string|null, gap_min: int}>
     */
    #[Computed]
    public function timeline(): Collection
    {
        if ($this->mode !== OrderMode::BUNDLE) {
            return collect();
        }

        return app(BundleComposer::class)->timeline(
            $this->draft(),
            $this->selectedDate ? Carbon::parse($this->selectedDate.' '.($this->selectedSlot ?? '08:00')) : null,
        );
    }

    /**
     * « Souvent commandé avec » — ce que l'administrateur a associé, moins ce qui est déjà là.
     *
     * @return Collection<int, array{trade: Trade, gap_min: int, after: string}>
     */
    #[Computed]
    public function bundleSuggestions(): Collection
    {
        return $this->mode === OrderMode::BUNDLE
            ? app(BundleComposer::class)->suggestionsFor($this->draft())
            : collect();
    }

    /**
     * Le devis consolidé : un total, le détail dépliable par métier, la remise visible.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function bundleQuote(): ?array
    {
        if ($this->mode !== OrderMode::BUNDLE || $this->draft()->items()->count() === 0) {
            return null;
        }

        return app(BundleComposer::class)->consolidatedQuote($this->draft());
    }

    /** Ajoute un métier au chantier, à sa place dans la séquence. */
    public function addService(int $tradeId): void
    {
        $trade = Trade::find($tradeId);

        if (! $trade || ! $trade->allowsMode(OrderMode::BUNDLE)) {
            return;
        }

        app(BundleComposer::class)->addTrade($this->draft(), $trade);

        // On bascule sur le métier ajouté : il faut répondre à SES questions, et le client vient
        // précisément de dire qu'il le voulait.
        $this->selectTrade($trade->id);
    }

    public function removeService(int $itemId): void
    {
        $item = $this->draft()->items()->find($itemId);

        if (! $item) {
            return;
        }

        app(BundleComposer::class)->removeTrade($this->draft(), $item);

        // Le métier retiré était peut-être celui affiché : on ne laisse pas un questionnaire
        // orphelin à l'écran.
        if ($this->tradeId === $item->trade_id) {
            $this->tradeId = null;
            $this->answers = [];
        }

        $this->refreshDerived();
    }

    /**
     * Réordonne le chantier — en refusant ce qui le casserait.
     *
     * @param  list<int|string>  $orderedItemIds
     */
    public function reorderServices(array $orderedItemIds): void
    {
        $this->sequenceError = '';

        try {
            app(BundleComposer::class)->reorder(
                $this->draft(),
                collect($orderedItemIds)->map(fn ($id) => (int) $id)->all(),
            );
        } catch (ValidationException $e) {
            $this->sequenceError = $e->getMessage();
        }

        $this->refreshDerived();
    }

    // ─── Navigation ──────────────────────────────────────────────────────────────────────────

    public function selectSector(int $sectorId): void
    {
        $this->sectorId = $sectorId;
        $this->tradeId = null;
        $this->answers = [];
        $this->refreshDerived();
    }

    /**
     * Rouvre l'etape du domaine depuis son resume replie.
     *
     * `selectSector(0)` aurait suffi a l'affichage — mais laisserait `sectorId = 0`, et le
     * parcours irait chercher un secteur d'identifiant zero a chaque rendu.
     */
    public function changerDeSecteur(): void
    {
        $this->sectorId = null;
        $this->tradeId = null;
        $this->answers = [];
        $this->refreshDerived();
    }

    public function selectTrade(?int $tradeId): void
    {
        $trade = $tradeId ? Trade::find($tradeId) : null;

        if (! $trade) {
            return;
        }

        $this->tradeId = $trade->id;
        $this->sectorId = $trade->sector_id ?? $this->sectorId;

        // L'intention arrivée par l'URL s'applique ICI, et pas avant.
        if ($this->intendedMode !== null) {
            $wanted = $this->intendedMode;
            $this->intendedMode = null;

            if ($trade->allowsMode($wanted)) {
                $this->mode = $wanted;
            } else {
                // On le DIT. Basculer en silence laisserait le client croire qu'il a commandé une
                // intervention dans l'heure.
                // Le métier est nommé comme la TUILE que le client vient de choisir.
                $this->modeNotice = match ($wanted) {
                    OrderMode::ASAP => sprintf(
                        '« %s » n’accepte pas les interventions immédiates : ce métier demande une préparation. Choisissez une date ci-dessous.',
                        $trade->translate('name'),
                    ),
                    OrderMode::BUNDLE => sprintf(
                        '« %s » ne se commande pas au sein d’un chantier multi-services. Il reste commandable seul.',
                        $trade->translate('name'),
                    ),
                    default => '',
                };
            }
        }

        // Le mode retenu peut ne pas exister sur ce métier : on retombe sur le planifié plutôt que
        // de proposer un immédiat que le serveur refuserait plus tard.
        if (! $trade->allowsMode($this->mode)) {
            $this->mode = OrderMode::SCHEDULED;
        }

        // Le mode est ÉCRIT SUR LA COMMANDE, pas seulement porté par l'écran.
        $draft = $this->draft();

        if ($draft->mode !== $this->mode) {
            $draft->update(['mode' => $this->mode]);
        }

        // CHOISIR UN MÉTIER L'INSCRIT AU PANIER — désormais dans tous les modes.
        if ($this->mode === OrderMode::BUNDLE) {
            app(BundleComposer::class)->addTrade($this->draft(), $trade);
        } else {
            app(OrderDraftManager::class)->itemFor($this->draft(), $trade);
        }

        $this->answers = $this->loadAnswers($trade);

        // Un questionnaire s'ouvre à son début : garder le rang du précédent poserait le client
        // au milieu de questions auxquelles il n'a jamais accédé.
        $this->stepIndex = 0;

        // Le professionnel déjà choisi pour ce métier se retrouve : revenir en arrière ne perd pas
        // plus un choix de prestataire qu'une réponse au questionnaire.
        $this->selectedProviderId = $this->draft()->items()
            ->where('trade_id', $trade->id)->value('provider_id');

        $this->refreshDerived();
    }

    /** Retour au dock, SANS perdre les réponses. */
    public function backToTrades(): void
    {
        $this->tradeId = null;
        $this->answers = [];
        $this->refreshDerived();
    }

    /** LE MODE CHOISI À L'ENTRÉE — avant même de savoir de quel métier il s'agit. */
    public function chooseIntent(?string $mode): void
    {
        $this->intendedMode = in_array($mode, OrderMode::all(), true) ? $mode : null;
        $this->modeNotice = '';

        $this->sectorId = null;
        $this->tradeId = null;
        $this->answers = [];
        $this->stepIndex = 0;

        unset($this->sectors, $this->trades);
        $this->refreshDerived();
    }

    /** Le catalogue est-il restreint à une intention ? */
    #[Computed]
    public function intentIsNarrowing(): bool
    {
        return $this->intendedMode !== null && $this->intendedMode !== OrderMode::SCHEDULED;
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, $this->availableModes, true)) {
            return;
        }

        $this->mode = $mode;
        $this->draft()->update(['mode' => $mode]);

        // Basculer en multi-services POSE au chantier le métier en cours de configuration.
        if ($mode === OrderMode::BUNDLE && $this->trade) {
            app(BundleComposer::class)->addTrade($this->draft(), $this->trade);
        }

        $this->refreshDerived();
    }

    // ─── Réponses ────────────────────────────────────────────────────────────────────────────

    /** Une réponse arrive du rendu client — le même composant que celui de l'aperçu admin. */
    #[On('question-answered')]
    public function recordAnswer(string $code, mixed $value, bool $valid): void
    {
        $this->answers[$code] = $value;

        // AVANT `persist()`, et l'ordre compte : le point de départ résout la zone, et c'est la
        // zone qui décide de la grille tarifaire. Enregistrée après, la ligne serait chiffrée au
        // tarif national puis corrigée au coup suivant — le client verrait deux prix.
        $this->enregistrerLaLocalisation($code, $value);

        $this->persist();
        $this->refreshDerived();
    }

    // ─── Trajet : les deux points, et la route entre eux ─────────────────────────────────────

    /** Ce métier décrit-il un trajet ? */
    #[Computed]
    public function estUnTrajet(): bool
    {
        $trade = $this->trade;

        return $trade !== null && TradeRouteRules::estUnTrajet($trade->loadMissing('questions'));
    }

    /**
     * La route retenue pour cette commande, telle qu'on l'annonce au client.
     *
     * @return array{distance_km: float, duration_min: int|null, source: string|null, approximatif: bool}|null
     */
    #[Computed]
    public function route(): ?array
    {
        $draft = $this->draft();

        if ($draft->route_distance_m === null) {
            return null;
        }

        return [
            'distance_km' => round($draft->route_distance_m / 1000, 1),
            'duration_min' => $draft->route_duration_s === null
                ? null
                : (int) ceil($draft->route_duration_s / 60),
            'source' => $draft->route_source,
            // Une ligne droite ne doit pas se faire passer pour un trajet routier : le dire permet
            // à l'écran de nuancer « environ » plutôt que d'annoncer une durée qu'on ne tiendra pas.
            'approximatif' => $draft->route_source === RoutingService::SOURCE_STRAIGHT,
        ];
    }

    /** Une réponse de localisation vient d'arriver : elle alimente la géographie de la commande. */
    /** PLACER UN POINT DEPUIS LA CARTE — le geste que le champ d'adresse ne remplace pas. */
    public function placerSurLaCarte(string $role, float $lat, float $lng): void
    {
        $trade = $this->trade;

        if ($trade === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return;
        }

        $trade->loadMissing('questions');

        $question = match ($role) {
            LocationRole::PICKUP => TradeRouteRules::questionDepart($trade),
            LocationRole::DROPOFF => TradeRouteRules::questionArrivee($trade),
            default => null,
        };

        if ($question === null) {
            return;
        }

        // Diffusé à TOUTES les questions, filtré par code à l'arrivée : les rendus de question sont autant d'instances du même composant, `->to()` ne saurait pas laquelle viser.
        $this->dispatch('place-location', code: $question->code, lat: $lat, lng: $lng);
    }

    /**
     * La géométrie de la route, pour que la carte trace un trajet et non une corde tendue.
     *
     * @return list<array{lat: float, lng: float}>
     */
    #[Computed]
    public function pointsDeLaRoute(): array
    {
        $draft = $this->draft();

        if ($draft->lat === null || $draft->lng === null
            || $draft->dropoff_lat === null || $draft->dropoff_lng === null) {
            return [];
        }

        try {
            return app(RoutingService::class)->geometry(
                (float) $draft->lat,
                (float) $draft->lng,
                (float) $draft->dropoff_lat,
                (float) $draft->dropoff_lng,
            ) ?? [];
        } catch (\Throwable $e) {
            Log::warning('[order_engine] géométrie de route indisponible', ['error' => $e->getMessage()]);

            return [];
        }
    }

    protected function enregistrerLaLocalisation(string $code, mixed $value): void
    {
        $question = $this->questions->firstWhere('code', $code);

        if (! $question || ! $question->isLocation() || ! is_array($value)) {
            return;
        }

        $lat = $value['lat'] ?? null;
        $lng = $value['lng'] ?? null;

        if ($lat === null || $lng === null) {
            return;
        }

        if ($question->location_role === LocationRole::DROPOFF) {
            $this->draft()->update([
                'dropoff_address' => $value['label'] ?? null,
                'dropoff_lat' => (float) $lat,
                'dropoff_lng' => (float) $lng,
                'dropoff_postal_code' => $value['postal_code'] ?? null,
            ]);

            $this->mesurerLaRoute();
            $this->annoncerLeTrajet();

            return;
        }

        if ($question->location_role !== LocationRole::PICKUP) {
            return;
        }

        $this->address = (string) ($value['label'] ?? $this->address);
        $this->addressUnresolved = false;
        $this->lat = (float) $lat;
        $this->lng = (float) $lng;

        $this->draft()->update([
            'address' => $this->address ?: null,
            'lat' => $this->lat,
            'lng' => $this->lng,
        ]);

        // Le code postal de la réponse est une commodité : la zone, elle, se résout par le chemin
        // unique déjà en place — trois résolutions séparées finiraient par diverger, et le prix
        // dépendrait de la façon dont le client a saisi son adresse.
        if (filled($value['postal_code'] ?? null)) {
            $this->postalCode = (string) $value['postal_code'];
        }

        $this->resolveGeographyFromCoordinates($this->lat, $this->lng);
        $this->mesurerLaRoute();
        $this->annoncerLeTrajet();
    }

    /** DIRE À LA CARTE QUE LES POINTS ONT BOUGÉ. */
    protected function annoncerLeTrajet(): void
    {
        if (! $this->estUnTrajet) {
            return;
        }

        $draft = $this->draft();
        $itineraire = $this->route;

        $this->dispatch('trajet-mis-a-jour', trajet: [
            'depart' => $draft->lat !== null && $draft->lng !== null
                ? ['lat' => (float) $draft->lat, 'lng' => (float) $draft->lng]
                : null,
            'arrivee' => $draft->dropoff_lat !== null && $draft->dropoff_lng !== null
                ? ['lat' => (float) $draft->dropoff_lat, 'lng' => (float) $draft->dropoff_lng]
                : null,
            'trace' => $this->pointsDeLaRoute,
            'approximatif' => (bool) ($itineraire['approximatif'] ?? true),
        ]);
    }

    /** Mesure la route dès que les deux points sont connus, et l'écrit sur le panier. */
    protected function mesurerLaRoute(): void
    {
        $draft = $this->draft();

        if ($draft->lat === null || $draft->lng === null
            || $draft->dropoff_lat === null || $draft->dropoff_lng === null) {
            return;
        }

        try {
            $route = app(RoutingService::class)->route(
                (float) $draft->lat,
                (float) $draft->lng,
                (float) $draft->dropoff_lat,
                (float) $draft->dropoff_lng,
            );

            $draft->update([
                'route_distance_m' => $route->distanceMeters,
                'route_duration_s' => $route->durationSeconds,
                'route_source' => $route->source,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[order_engine] itinéraire indisponible', ['error' => $e->getMessage()]);
        }

        unset($this->route);
    }

    protected function persist(): void
    {
        $trade = $this->trade;

        if (! $trade) {
            return;
        }

        $manager = app(OrderDraftManager::class);
        $draft = $this->draft();
        $item = $manager->itemFor($draft, $trade);

        $manager->saveAnswers($item, $this->questions, $this->answers);
        $manager->reprice($draft);
    }

    /** Le métier courant se facture-t-il au temps passé ? */
    public function estFactureALHeure(): bool
    {
        return (bool) $this->trade?->hourly_billing;
    }

    /** Le tarif horaire applicable ici — métier, ou surcharge de la zone quand elle existe. */
    public function tarifHoraireCents(): ?int
    {
        $trade = $this->trade;

        if (! $trade) {
            return null;
        }

        return app(HourlyRateResolver::class)->tarifCatalogue($trade, $this->serviceZoneId);
    }

    /** Les heures choisies, en minutes — la forme que le moteur attend. */
    public function heuresEnMinutes(): ?int
    {
        if (! $this->estFactureALHeure() || $this->heuresChoisies === null) {
            return null;
        }

        return (int) round($this->heuresChoisies * 60);
    }

    /** Le client choisit sa durée. BORNÉE DES DEUX CÔTÉS. */
    public function choisirLesHeures(float $heures): void
    {
        $min = (float) Config::get('order_engine.hourly_min_hours', 1.0);
        $max = (float) Config::get('order_engine.hourly_max_hours', 12.0);
        $pas = $this->pasDuSelecteur();

        $this->heuresChoisies = max($min, min($max, round($heures / $pas) * $pas));

        $this->enregistrerLesHeures();
        $this->refreshDerived();
    }

    public function ajouterUneDemiHeure(): void
    {
        $this->choisirLesHeures(($this->heuresChoisies ?? $this->heuresParDefaut()) + $this->pasDuSelecteur());
    }

    public function retirerUneDemiHeure(): void
    {
        $this->choisirLesHeures(($this->heuresChoisies ?? $this->heuresParDefaut()) - $this->pasDuSelecteur());
    }

    /** LE MEME PAS QUE LA PROLONGATION, et c'est la raison pour laquelle il vient de la configuration : acheter du temps a la commande et en acheter pendant la mission sont le meme geste. */
    public function pasDuSelecteur(): float
    {
        $pas = (float) Config::get('order_engine.hourly_step_hours', 0.5);

        // Un pas nul ou negatif ferait une division par zero dans l'arrondi ci-dessus : la valeur
        // vient d'une variable d'environnement, elle n'est pas garantie.
        return $pas > 0 ? $pas : 0.5;
    }

    /** La durée proposée d'entrée : celle que le métier estime, arrondie à la demi-heure. */
    public function heuresParDefaut(): float
    {
        $estimation = (int) ($this->trade->estimated_duration_min ?? 0);
        $min = (float) Config::get('order_engine.hourly_min_hours', 1.0);

        if ($estimation <= 0) {
            return $min;
        }

        $pas = $this->pasDuSelecteur();

        return max($min, round(($estimation / 60) / $pas) * $pas);
    }

    /** Les heures voyagent sur la LIGNE DE PANIER, pas sur le panier. */
    protected function enregistrerLesHeures(): void
    {
        $trade = $this->trade;

        if (! $trade || $this->heuresChoisies === null) {
            return;
        }

        $item = $this->draft()->items()->where('trade_id', $trade->id)->first();

        $item?->forceFill(['purchased_minutes' => $this->heuresEnMinutes()])->save();
    }

    /** @return array<string, mixed> */
    protected function loadAnswers(Trade $trade): array
    {
        $item = $this->draft()->items()->where('trade_id', $trade->id)->with('answers')->first();

        return $item ? app(OrderDraftManager::class)->answersOf($item) : [];
    }

    public function draft(): OrderDraft
    {
        return app(OrderDraftManager::class)->resumeOrCreate(
            $this->sessionToken,
            Auth::user(),
            $this->mode,
        );
    }

    protected function refreshDerived(): void
    {
        unset(
            $this->trades, $this->trade, $this->questions, $this->visibleQuestions,
            $this->quote, $this->lastChange, $this->availableModes, $this->availability, $this->steps, $this->allVisibleQuestions,
            $this->slots, $this->providerOptions, $this->readyToConfirm, $this->dayOptions,
            $this->timeline, $this->bundleSuggestions, $this->bundleQuote,
            $this->addressSuggestions, $this->estUnTrajet, $this->route,
        );
    }

    public function render(): View
    {
        return view('livewire.order-engine.order-journey');
    }
}
