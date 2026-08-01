<?php

namespace App\Livewire\OrderEngine;

use App\Models\OrderDraft;
use App\Models\Sector;
use App\Models\Trade;
use App\Services\GeolocationV2\GeocodingService;
use App\Services\OrderEngine\AvailabilitySnapshot;
use App\Services\OrderEngine\ConditionEvaluator;
use App\Services\OrderEngine\OrderDraftManager;
use App\Services\OrderEngine\PriceBreakdown;
use App\Services\OrderEngine\PricingEngine;
use App\Services\OrderEngine\ProviderAvailabilityLookup;
use App\Services\OrderEngine\ProviderShortlist;
use App\Services\OrderEngine\SlotFinder;
use App\Support\Domain\OrderMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
#[Layout('layouts.app')]
class OrderJourney extends Component
{
    /** Le jeton qui rattache un visiteur à son panier, sans compte. */
    public string $sessionToken = '';

    public ?int $sectorId = null;

    public ?int $tradeId = null;

    /** Réponses en cours, indexées par code de question. */
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

    /** Le géocodage a échoué : on le dit, plutôt que de laisser un champ muet. */
    public bool $addressUnresolved = false;

    /** Jour retenu pour l'intervention, au format ISO. */
    public ?string $selectedDate = null;

    /** Heure de début du créneau retenu, au format H:i. */
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

    /** @return Collection<int, Sector> */
    #[Computed]
    public function sectors()
    {
        return Sector::query()
            ->active()
            ->ordered()
            ->withCount(['trades' => fn ($q) => $q->where('is_active', true)])
            ->get();
    }

    /** Les métiers du secteur retenu — ceux du dock. */
    #[Computed]
    public function trades()
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
     */
    #[Computed]
    public function questions()
    {
        $trade = $this->trade();

        if (! $trade) {
            return collect();
        }

        $query = $trade->questions()->with(['options', 'conditions'])->where('is_active', true);

        if ($this->mode === OrderMode::ASAP) {
            $query->where('is_essential', true);
        }

        return $query->orderBy('sort_order')->get();
    }

    /** Celles réellement affichées : une condition non remplie masque sa question. */
    #[Computed]
    public function visibleQuestions()
    {
        return app(ConditionEvaluator::class)
            ->visible($this->questions(), $this->answers);
    }

    /** Les modes que ce métier autorise. Un ravalement de façade n'est pas un service immédiat. */
    #[Computed]
    public function availableModes(): array
    {
        $trade = $this->trade();

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
        $trade = $this->trade();

        return $trade
            ? app(PricingEngine::class)->quoteItem($trade, $this->questions(), $this->answers, ['mode' => $this->mode])
            : null;
    }

    /** Ce que la dernière réponse a changé — « +45 € — plafonds inclus ». */
    #[Computed]
    public function lastChange(): ?array
    {
        $quote = $this->quote();

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
        $trade = $this->trade();

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
     */
    #[Computed]
    public function slots(): array
    {
        $trade = $this->trade();

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

    /** Les professionnels proposés, pour qui veut choisir. La liste reste facultative. */
    #[Computed]
    public function providerOptions()
    {
        $trade = $this->trade();

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
        $slot = collect($this->slots())->first(
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
        if ($providerId !== null && ! $this->providerOptions()->contains('id', $providerId)) {
            return;
        }

        $this->selectedProviderId = $providerId;

        /*
         * Le choix est ÉCRIT sur la ligne, pas seulement gardé à l'écran. Un état qui ne vit que
         * dans le composant disparaît au rechargement — et le client, lui, croit avoir choisi son
         * professionnel. C'est aussi ce qui permet à la pré-autorisation de partir dès la
         * confirmation : sans prestataire enregistré, Stripe n'a pas de destination.
         */
        if ($trade = $this->trade()) {
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
        return $this->trade() !== null
            && $this->lat !== null
            && $this->selectedDate !== null
            && $this->selectedSlot !== null;
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
        if (in_array($mode, $this->availableModes(), true)) {
            $this->mode = $mode;
            $this->draft()->update(['mode' => $mode]);
            $this->refreshDerived();
        }
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
        $trade = $this->trade();

        if (! $trade) {
            return;
        }

        $manager = app(OrderDraftManager::class);
        $draft = $this->draft();
        $item = $manager->itemFor($draft, $trade);

        $manager->saveAnswers($item, $this->questions(), $this->answers);
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
        );
    }

    public function render()
    {
        return view('livewire.order-engine.order-journey');
    }
}
