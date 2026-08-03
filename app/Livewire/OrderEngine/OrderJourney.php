<?php

namespace App\Livewire\OrderEngine;

use App\Models\OrderDraft;
use App\Models\OrderDraftItem;
use App\Models\OrderDraftMedia;
use App\Models\Question;
use App\Models\Sector;
use App\Models\Trade;
use App\Services\GeolocationV2\AddressSuggestion;
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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

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

    /**
     * Photos en attente d'être jointes.
     *
     * `WithFileUploads` est ce qui manquait : sans lui, `wire:model` sur un champ fichier ne fait
     * rien du tout — pas d'erreur, pas de fichier, rien.
     *
     * @var array<int, mixed>
     */
    public array $photos = [];

    /**
     * Mode demandé par l'URL, en attente d'un métier.
     *
     * L'application mobile n'a plus d'écran de réservation natif : ses trois cartes d'entrée —
     * immédiat, rendez-vous, multi-services — ouvrent toutes ce parcours. Sans cette intention,
     * elles arriveraient toutes sur le même écran planifié et le choix d'entrée serait décoratif :
     * le client demanderait « immédiat », puis devrait le redemander.
     *
     * Elle ne s'applique pas tout de suite : les modes disponibles dépendent du métier, et aucun
     * n'est choisi tant que le client regarde le carrousel.
     */
    public ?string $intendedMode = null;

    /** Ce qu'on doit au client quand son intention n'a pas pu être honorée. */
    public string $modeNotice = '';

    /**
     * L'étape du questionnaire en cours d'affichage.
     *
     * Remise à zéro à chaque changement de métier : garder le rang d'un questionnaire précédent
     * ouvrirait le suivant au milieu, sur des questions auxquelles le client n'a jamais accédé.
     */
    public int $stepIndex = 0;

    public function mount(?string $sector = null, ?string $trade = null): void
    {
        /*
         * Une valeur inventée dans l'URL est ignorée, sans rien casser : la barre d'adresse est
         * une entrée comme une autre, et rien de ce qui en vient n'est cru sur parole.
         */
        $requested = (string) request()->query('mode', '');

        if (in_array($requested, OrderMode::all(), true)) {
            $this->intendedMode = $requested;
        }

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
        $sectors = Sector::query()
            ->active()
            ->ordered()
            ->withCount(['trades' => fn ($q) => $q->where('is_active', true)])
            ->get();

        /*
         * Le signal vivant des cartes.
         *
         * « 3 métiers » est un fait de catalogue : le client ne peut ni le vérifier ni s'en
         * servir. La confiance vient de la disponibilité VISIBLE, pas d'un décompte de rubriques.
         *
         * EN UNE REQUÊTE, pas une par carte : c'est le premier écran du produit, celui dont dépend
         * le LCP, et le nombre de secteurs n'a pas de plafond.
         */
        $counts = $this->activeProvidersPerSector();

        return $sectors->each(function (Sector $sector) use ($counts) {
            $sector->setAttribute('active_providers_count', (int) ($counts[$sector->id] ?? 0));
        });
    }

    /**
     * Combien de professionnels actifs exercent dans chaque secteur.
     *
     * La définition est reprise TELLE QUELLE de {@see ProviderAvailabilityLookup} — profil actif,
     * rôle prestataire ou employé. Deux définitions divergentes afficheraient 42 sur la carte et 0
     * une fois l'adresse saisie, et c'est le second chiffre que le client retiendrait.
     *
     * `distinct` sur l'utilisateur : un professionnel qui exerce deux métiers du même secteur
     * compte pour un. Le compter deux fois gonflerait la promesse d'autant, et le client s'en
     * apercevrait au premier créneau introuvable.
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

        $query = $trade->questions()->with(['options.translations', 'conditions', 'translations', 'step'])->where('is_active', true);

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
     * À distinguer de {@see visibleQuestions()}, qui ne rend que l'étape courante — c'est elle que
     * l'écran affiche. Celle-ci sert à raisonner sur le questionnaire entier : le prix se calcule
     * sur l'ensemble, pas sur ce que le client a sous les yeux à cet instant.
     *
     * @return Collection<int, Question>
     */
    #[Computed]
    public function allVisibleQuestions(): Collection
    {
        return app(ConditionEvaluator::class)->visible($this->questions, $this->answers);
    }

    /**
     * Le questionnaire découpé en étapes RÉELLEMENT visibles.
     *
     * Deux règles, dans cet ordre.
     *
     * 1. Les étapes écrites par l'administrateur priment. C'est lui qui sait où couper : « vos
     *    locaux », puis « la prestation ».
     *
     * 2. À défaut, on découpe TOUT SEUL au-delà du seuil. Le validateur avertit déjà celui qui
     *    dépasse sept questions, mais ce n'est qu'une alerte : faire reposer la règle sur sa
     *    discipline reviendrait à ne pas l'avoir. Un métier de douze questions afficherait douze
     *    champs empilés — l'anti-pattern que le parcours est censé éviter.
     *
     * Les étapes vides ne comptent pas et ne se traversent pas : une étape dont toutes les
     * questions sont masquées par une condition n'existe plus pour ce client. Annoncer « étape 2
     * sur 3 » puis sauter la troisième serait un compte malhonnête.
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

        // L'administrateur a-t-il vraiment découpé ? Une seule étape déclarée — ou aucune — ne
        // compte pas comme un découpage : on reprend la main.
        $hasRealSteps = $declared->keys()->filter(fn ($id) => $id !== null && $id !== '')->count() > 0
            && $declared->count() > 1;

        if ($hasRealSteps) {
            return $declared
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
        /*
         * Revenir ne perd rien : les réponses vivent dans le panier, en base, pas dans l'écran.
         * C'est toute la raison pour laquelle l'état n'habite pas le composant.
         */
        if ($this->stepIndex > 0) {
            $this->stepIndex--;
        }
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

    // ─── Rattrapage du panier ────────────────────────────────────────────────────────────────

    /**
     * La clé à confier au navigateur, pour retrouver ce panier si le cookie disparaît.
     *
     * Le cookie de session reste la voie normale — il est `httpOnly`, donc hors de portée d'une
     * XSS. Cette clé-ci est un RATTRAPAGE, et c'est pour cela qu'elle est bornée : hachée en base,
     * tournante à chaque usage, expirante. Sans ces trois limites, ce serait le jeton de session
     * recopié en clair dans `localStorage`, à la portée de tout script injecté, et pour toujours.
     */
    #[Computed(persist: false)]
    public function recoveryKey(): ?string
    {
        $manager = app(OrderDraftManager::class);

        return $manager->issueRecoveryKey($this->draft());
    }

    /**
     * Le navigateur présente une clé : on rouvre le panier qu'elle désigne.
     *
     * Appelé uniquement quand la session n'a rien — un cookie effacé, une session expirée. Une clé
     * inconnue, périmée ou pointant sur une commande déjà passée ne fait RIEN : il n'y a personne
     * à informer, et le client garde le panier vide qu'il avait.
     */
    public function recoverDraft(string $key): void
    {
        $manager = app(OrderDraftManager::class);
        $recovered = $manager->recoverByKey($key);

        if (! $recovered) {
            return;
        }

        /*
         * On adopte le jeton du panier retrouvé plutôt que d'y recopier le nôtre : le reste du
         * composant travaille par jeton de session, et la reprise redevient ainsi le chemin
         * ordinaire, sans cas particulier.
         */
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

    /**
     * Le client fixe la date d'UN métier du chantier.
     *
     * La séquence calculée reste le défaut — il n'a pas à orchestrer ses artisans. Mais quand le
     * plombier ne peut que mardi, il doit pouvoir le dire sans renoncer au reste.
     *
     * Le refus est AFFICHÉ, jamais corrigé en silence : une date rectifiée sans le dire ferait
     * croire au client que la sienne a été prise, et il découvrirait autre chose le jour venu.
     */
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

    /**
     * Joint les photos choisies à la ligne de commande du métier courant.
     *
     * Le champ existait, `order_draft_media` existait, le modèle et la relation aussi — et rien ne
     * les reliait. `wire:model` sur un `<input type="file">` sans le trait d'upload ne fait
     * strictement rien : le client choisissait une photo, lisait « Envoi en cours… », et le fichier
     * disparaissait. Sans erreur, sans trace, et sans que le prestataire n'en voie la couleur.
     *
     * Le refus d'un fichier non conforme est ANNONCÉ. Un refus muet fait recommencer trois fois
     * avec le même fichier.
     */
    public function attachPhotos(): void
    {
        if (! $this->trade || $this->photos === []) {
            return;
        }

        $this->validate(
            ['photos.*' => ['image', 'mimes:jpeg,jpg,png,webp,heic', 'max:8192']],
            [
                'photos.*.image' => 'Seules les photos sont acceptées ici (JPEG, PNG, WebP).',
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

    /**
     * Le client change d'avis.
     *
     * Le fichier part avec la ligne : garder l'un sans l'autre laisserait des images orphelines sur
     * le disque, invisibles et jamais purgées — et il s'agit de photos du domicile de quelqu'un.
     */
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
     * Sans aperçu, on rejoint deux fois la même : le client n'a aucun moyen de savoir ce qui est
     * déjà parti.
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
     * Le champ était nu, et faire taper une adresse entière au pouce accepte d'avance les fautes de
     * frappe. Or une faute de frappe fait échouer le géocodage EN SILENCE — par conception, pour ne
     * jamais bloquer une commande : on perd la preuve de disponibilité, et le prestataire part à la
     * mauvaise porte.
     *
     * La liste se tait dès que l'adresse est située : proposer autre chose à quelqu'un qui a déjà
     * choisi ne l'aide plus, ça le fait douter.
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

            /*
             * Une seule proposition, identique à ce qui est écrit : le client a déjà choisi. Lui
             * reproposer sa propre saisie ne l'aide plus, ça le fait douter d'avoir bien fait.
             *
             * Le critère n'est PAS « l'adresse est située » : pendant la frappe, une adresse
             * partielle se géocode souvent avec succès sur une ville entière, et masquer les
             * suggestions à ce moment-là retire l'aide juste avant qu'elle ne serve.
             */
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

    /**
     * Le client retient une suggestion : elle porte déjà sa position.
     *
     * Relancer un géocodage sur un libellé qu'on vient de fournir soi-même paierait un appel de
     * plus pour un résultat déjà en main — et s'exposerait à ce qu'il échoue là où le premier avait
     * réussi.
     */
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
        $this->refreshDerived();
    }

    /**
     * « Utiliser ma position » — le client a déjà l'information dans sa poche.
     *
     * Le navigateur fournit les coordonnées, le serveur les retourne en adresse lisible. Sur un
     * téléphone, c'est un geste contre une adresse entière tapée au pouce.
     *
     * Les coordonnées sont retenues MÊME si le serveur ne sait pas les nommer : ce sont elles qui
     * débloquent la preuve de disponibilité et le rayon de recherche, le libellé n'est qu'un
     * confort de lecture.
     */
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

        /*
         * L'intention arrivée par l'URL s'applique ICI, et pas avant.
         *
         * Les modes dépendent du métier — un ravalement de façade n'est pas un service immédiat —
         * et aucun n'est choisi tant que le client est sur le carrousel. L'intention attend donc la
         * sélection, puis se consomme : elle ne doit pas se réappliquer à chaque changement de
         * métier, sinon le client qui bascule volontairement en planifié se ferait ramener en
         * immédiat au métier suivant.
         */
        if ($this->intendedMode !== null) {
            $wanted = $this->intendedMode;
            $this->intendedMode = null;

            if ($trade->allowsMode($wanted)) {
                $this->mode = $wanted;
            } else {
                // On le DIT. Basculer en silence laisserait le client croire qu'il a commandé une
                // intervention dans l'heure.
                $this->modeNotice = match ($wanted) {
                    OrderMode::ASAP => sprintf(
                        '« %s » n’accepte pas les interventions immédiates : ce métier demande une préparation. Choisissez une date ci-dessous.',
                        $trade->name,
                    ),
                    OrderMode::BUNDLE => sprintf(
                        '« %s » ne se commande pas au sein d’un chantier multi-services. Il reste commandable seul.',
                        $trade->name,
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

        /*
         * Le mode est ÉCRIT SUR LA COMMANDE, pas seulement porté par l'écran.
         *
         * `reprice()` recalcule à partir de `order_drafts.mode` et jamais de la propriété du
         * composant. Les deux qui divergent, c'est l'écran qui annonce une majoration d'urgence
         * pendant que le devis enregistré — celui que la confirmation reprend — est calculé au
         * tarif planifié : le client voit un prix et en paie un autre.
         *
         * Le repli ci-dessus produisait exactement cela : passer d'un métier qui accepte
         * l'immédiat à un métier qui le refuse ramenait l'écran au planifié en laissant la commande
         * majorée.
         */
        $draft = $this->draft();

        if ($draft->mode !== $this->mode) {
            $draft->update(['mode' => $this->mode]);
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

        // Un questionnaire s'ouvre à son début : garder le rang du précédent poserait le client
        // au milieu de questions auxquelles il n'a jamais accédé.
        $this->stepIndex = 0;

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
            $this->quote, $this->lastChange, $this->availableModes, $this->availability, $this->steps, $this->allVisibleQuestions,
            $this->slots, $this->providerOptions, $this->readyToConfirm, $this->dayOptions,
            $this->timeline, $this->bundleSuggestions, $this->bundleQuote,
            $this->addressSuggestions,
        );
    }

    public function render(): View
    {
        return view('livewire.order-engine.order-journey');
    }
}
