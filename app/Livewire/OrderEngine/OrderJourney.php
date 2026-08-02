<?php

namespace App\Livewire\OrderEngine;

use App\Models\OrderDraft;
use App\Models\OrderDraftItem;
use App\Models\Question;
use App\Models\Sector;
use App\Models\Trade;
use App\Services\GeolocationV2\GeocodingService;
use App\Services\OrderEngine\AvailabilitySnapshot;
use App\Services\OrderEngine\BundleComposer;
use App\Services\OrderEngine\ConditionEvaluator;
use App\Services\OrderEngine\OrderDraftManager;
use App\Services\OrderEngine\PriceBreakdown;
use App\Services\OrderEngine\PricingEngine;
use App\Services\OrderEngine\ProviderAvailabilityLookup;
use App\Services\OrderEngine\ProviderShortlist;
use App\Services\OrderEngine\SlotFinder;
use App\Support\Domain\OrderMode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Le parcours de commande : secteur, puis métier, puis questions — sans changer de page.
 *
 * L'ordre des écrans n'est pas une commodité de développeur, c'est la première loi du parcours :
 * le client voit une ESTIMATION avant qu'on lui demande son nom, son téléphone ou sa carte. Un
 * prix caché derrière un formulaire de compte est la première cause d'abandon, et rien dans ce
 * composant ne réclame d'identité.
 *
 * Rien n'est perdu non plus. Le panier vit en base, retrouvé par un jeton de session : fermer
 * l'onglet, revenir trois heures plus tard, se connecter au dernier moment — les réponses sont là.
 * C'est pour cela que l'état n'habite pas le composant mais le panier.
 */
/**
 * Les valeurs calculées, accessibles en PROPRIÉTÉ.
 *
 * `#[Computed]` ne met en cache que l'accès propriété : `$this->trade` mémorise, `$this->trade()`
 * réexécute le corps à chaque appel. Ces déclarations disent à l'analyse statique ce que Livewire
 * expose, et rappellent la forme à employer.
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
 */
#[Layout('layouts.app')]
class OrderJourney extends Component
{
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

    public string $mode = OrderMode::SCHEDULED;

    /**
     * L'adresse vit au niveau de la COMMANDE, pas de la ligne.
     *
     * En multi-services, elle n'est demandée qu'une fois : redemander la même adresse à chaque
     * métier ajouté est le genre de frottement qui fait abandonner un panier déjà rempli.
     */
    public string $address = '';

    public ?float $lat = null;

    public ?float $lng = null;

    /**
     * Le géocodage a échoué : on le dit, plutôt que de laisser un champ muet. */
    public bool $addressUnresolved = false;

    /**
     * Le refus de réordonnancement, AFFICHÉ : corriger en silence tromperait le client. */
    public string $sequenceError = '';

    /**
     * Jour retenu pour l'intervention, au format ISO. */
    public ?string $selectedDate = null;

    /**
     * Heure de début du créneau retenu, au format H:i. */
    public ?string $selectedSlot = null;

    /**
     * Prestataire choisi, ou `null` pour l'attribution automatique.
     *
     * `null` est le DÉFAUT et suffit pour continuer : obliger à trancher entre douze inconnus
     * transforme un service en corvée de comparaison, sur des critères que le client n'a aucun
     * moyen d'arbitrer.
     */
    public ?int $selectedProviderId = null;

    public function mount(?string $sector = null, ?string $trade = null): void
    {
        /*
         * Le jeton vit dans la SESSION, pas dans une propriété exposée : une propriété Livewire
         * voyage par le navigateur, et le panier de quelqu'un d'autre ne doit pas s'ouvrir en
         * changeant une valeur dans les outils de développement.
         */
        $this->sessionToken = session()->get('order_draft_token') ?: Str::random(48);
        session()->put('order_draft_token', $this->sessionToken);

        if ($sector) {
            $this->sectorId = Sector::where('slug', $sector)->value('id');
        }

        if ($trade) {
            $this->selectTrade((int) Trade::where('slug', $trade)->value('id'));
        }
    }

    // ─── Catalogue ───────────────────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, Sector> */
    #[Computed]
    public function sectors()
    {
        return Sector::query()
            ->active()
            ->ordered()
            ->withCount(['trades' => fn ($q) => $q->where('is_active', true)])
            ->get();
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
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function trade(): ?Trade
    {
        return $this->tradeId ? Trade::find($this->tradeId) : null;
    }

    /**
     * Les questions à poser, dans l'ordre.
     *
     * En mode immédiat le questionnaire est volontairement RÉDUIT aux questions essentielles : la
     * vitesse prime sur la précision, et la fourchette annoncée est simplement plus large. Poser
     * huit questions à quelqu'un dont l'eau coule dans le couloir serait absurde.
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

        $query = $trade->questions()->with(['options.translations', 'conditions', 'translations'])->where('is_active', true);

        if ($this->mode === OrderMode::ASAP) {
            $query->where('is_essential', true);
        }

        return $query->orderBy('sort_order')->get();
    }

    /** Celles réellement affichées : une condition non remplie masque sa question.
     *
     * @return Collection<int, Question>
     */
    #[Computed]
    public function visibleQuestions(): Collection
    {
        return app(ConditionEvaluator::class)
            ->visible($this->questions, $this->answers);
    }

    /** Les modes que ce métier autorise. Un ravalement de façade n'est pas un service immédiat.
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

        return collect(OrderMode::all())
            ->filter(fn (string $mode) => $trade->allowsMode($mode))
            ->values()
            ->all();
    }

    // ─── Prix ────────────────────────────────────────────────────────────────────────────────

    /**
     * L'estimation en direct, recalculée à chaque réponse.
     *
     * Le calcul est fait par le serveur, et lui seul fait autorité : rien de ce que le navigateur
     * annoncerait comme prix n'est lu ici.
     */
    #[Computed]
    public function quote(): ?PriceBreakdown
    {
        $trade = $this->trade;

        return $trade
            ? app(PricingEngine::class)->quoteItem($trade, $this->questions, $this->answers, ['mode' => $this->mode])
            : null;
    }

    /** Ce que la dernière réponse a changé — « +45 € — plafonds inclus ».
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

    /**
     * L'adresse débloque la preuve de disponibilité.
     *
     * Elle est posée en FIN de parcours, et c'est délibéré : elle récompense le client d'être allé
     * jusque-là par une information qui le rassure — « 14 peintres à moins de 8 km » — au lieu de
     * le filtrer à l'entrée.
     *
     * Le géocodage échoue en silence : une adresse mal orthographiée ou un service indisponible ne
     * doivent pas empêcher de commander. On perd la phrase rassurante, pas la commande.
     */
    public function updatedAddress(): void
    {
        $this->addressUnresolved = false;
        $this->lat = null;
        $this->lng = null;

        $address = trim($this->address);

        if (mb_strlen($address) < 6) {
            $this->refreshDerived();

            return;
        }

        try {
            $result = app(GeocodingService::class)->geocode($address, 'BE');

            if ($result) {
                $this->lat = $result->latitude;
                $this->lng = $result->longitude;
            } else {
                $this->addressUnresolved = true;
            }
        } catch (\Throwable $e) {
            $this->addressUnresolved = true;
            Log::warning('[order_engine] géocodage indisponible', ['error' => $e->getMessage()]);
        }

        $this->draft()->update([
            'address' => $address,
            'lat' => $this->lat,
            'lng' => $this->lng,
        ]);

        $this->refreshDerived();
    }

    /**
     * Ce qu'on peut honnêtement promettre. `null` tant que l'adresse n'est pas située.
     *
     * On ne promet rien avant de pouvoir le vérifier : une estimation de disponibilité affichée
     * sans position serait une décoration, et la loi 11 dit exactement le contraire.
     */
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
     * Les seconds ne sont pas retirés : masqués, ils laisseraient une grille trouée que le client
     * lirait comme une panne. Grisés avec leur raison, ils informent.
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

    /** Les professionnels proposés, pour qui veut choisir. La liste reste facultative.
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

        /*
         * Le choix est ÉCRIT sur la ligne, pas seulement gardé à l'écran. Un état qui ne vit que
         * dans le composant disparaît au rechargement — et le client, lui, croit avoir choisi son
         * professionnel. C'est aussi ce qui permet à la pré-autorisation de partir dès la
         * confirmation : sans prestataire enregistré, Stripe n'a pas de destination.
         */
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
     * L'ordre n'est pas cosmétique — le carreleur ne pose pas avant que le plombier ait fini, et
     * pas immédiatement après non plus : il faut laisser sécher. Le client doit VOIR ce
     * séquencement, sinon il croit que tout le monde arrive le même matin.
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

    /** « Souvent commandé avec » — ce que l'administrateur a associé, moins ce qui est déjà là.
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

    /** Le devis consolidé : un total, le détail dépliable par métier, la remise visible.
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
     * Le client peut passer le nettoyage avant la peinture ; il ne peut pas faire poser le
     * carrelage avant la plomberie. Le refus est AFFICHÉ : corriger en silence lui ferait croire
     * que son geste a été pris en compte.
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

    public function selectTrade(?int $tradeId): void
    {
        $trade = $tradeId ? Trade::find($tradeId) : null;

        if (! $trade) {
            return;
        }

        $this->tradeId = $trade->id;
        $this->sectorId = $trade->sector_id ?? $this->sectorId;

        // Le mode retenu peut ne pas exister sur ce métier : on retombe sur le planifié plutôt que
        // de proposer un immédiat que le serveur refuserait plus tard.
        if (! $trade->allowsMode($this->mode)) {
            $this->mode = OrderMode::SCHEDULED;
        }

        /*
         * En MULTI-SERVICES, choisir un métier le place immédiatement au chantier.
         *
         * Ailleurs, une ligne de panier n'apparaît qu'à la première réponse — regarder un métier
         * n'est pas le commander. Mais ici le client vient d'appuyer sur « ajouter un autre
         * service » : le métier doit figurer au plan tout de suite, sinon il compose son chantier
         * et n'y voit rien apparaître tant qu'il n'a pas répondu à une question.
         */
        if ($this->mode === OrderMode::BUNDLE) {
            app(BundleComposer::class)->addTrade($this->draft(), $trade);
        }

        $this->answers = $this->loadAnswers($trade);

        // Le professionnel déjà choisi pour ce métier se retrouve : revenir en arrière ne perd pas
        // plus un choix de prestataire qu'une réponse au questionnaire.
        $this->selectedProviderId = $this->draft()->items()
            ->where('trade_id', $trade->id)->value('provider_id');

        $this->refreshDerived();
    }

    /**
     * Retour au dock, SANS perdre les réponses.
     *
     * Elles vivent dans le panier, pas dans le composant : revenir en arrière puis rouvrir le même
     * métier retrouve exactement ce qui avait été saisi.
     */
    public function backToTrades(): void
    {
        $this->tradeId = null;
        $this->answers = [];
        $this->refreshDerived();
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, $this->availableModes, true)) {
            return;
        }

        $this->mode = $mode;
        $this->draft()->update(['mode' => $mode]);

        /*
         * Basculer en multi-services POSE au chantier le métier en cours de configuration.
         *
         * Le client vient de dire « en fait il m'en faut plusieurs » : celui qu'il regardait est le
         * premier du chantier. Sans ce geste, il passe en multi-services et trouve un plan vide,
         * alors qu'il venait de répondre à ses questions.
         */
        if ($mode === OrderMode::BUNDLE && $this->trade) {
            app(BundleComposer::class)->addTrade($this->draft(), $this->trade);
        }

        $this->refreshDerived();
    }

    // ─── Réponses ────────────────────────────────────────────────────────────────────────────

    /**
     * Une réponse arrive du rendu client — le même composant que celui de l'aperçu admin.
     *
     * Elle est enregistrée immédiatement. Attendre une validation finale ferait perdre tout le
     * questionnaire au premier onglet fermé, et c'est précisément ce que le parcours promet
     * d'éviter.
     */
    #[On('question-answered')]
    public function recordAnswer(string $code, mixed $value, bool $valid): void
    {
        $this->answers[$code] = $value;
        $this->persist();
        $this->refreshDerived();
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
            $this->quote, $this->lastChange, $this->availableModes, $this->availability,
            $this->slots, $this->providerOptions, $this->readyToConfirm, $this->dayOptions,
            $this->timeline, $this->bundleSuggestions, $this->bundleQuote,
        );
    }

    public function render(): View
    {
        return view('livewire.order-engine.order-journey');
    }
}
