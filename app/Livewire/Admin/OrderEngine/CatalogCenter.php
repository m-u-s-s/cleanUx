<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Models\Country;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Services\OrderEngine\CatalogArchiver;
use App\Services\OrderEngine\QuestionInsights;
use App\Services\OrderEngine\QuestionnaireValidator;
use App\Services\OrderEngine\TradeFormPublisher;
use App\Support\Livewire\Concerns\Admin\ManagesTradeForm;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use App\Services\Admin\SurgeOverviewService;
use Livewire\Component;

/**
 * Le catalogue : secteurs et métiers.
 *
 * C'est la porte d'entrée qui manquait — sans elle, le constructeur de parcours n'était atteignable
 * par aucun lien, et il fallait connaître une adresse pour l'ouvrir.
 *
 * L'écran ne se contente pas de lister. Il montre, pour chaque métier, ce qui empêche sa
 * publication et ce qui attend d'être mis en ligne : un catalogue où l'on doit ouvrir dix écrans
 * pour savoir lequel est prêt ne sera pas tenu à jour.
 *
 * IL EST DÉSORMAIS CELUI D'UNE ZONE. Les secteurs et les métiers restent communs — ce sont les
 * mêmes objets partout — mais leur OUVERTURE se règle zone par zone. C'est ce qui permet à un
 * métier de coûter plus cher là où la demande est forte : l'activation et le prix sont la même
 * ligne, `trade_zone_pricing`, donc il n'y a pas deux réglages à garder cohérents.
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

    /*
     * Le formulaire complet d'un métier — vingt et un champs — partagé avec `/admin/trades`.
     *
     * Le trait fournit les propriétés, les règles et la persistance ; l'enchaînement propre à cet
     * écran (rattacher au secteur, ouvrir dans la zone) reste ci-dessous.
     */
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
        /*
         * L'URL porte les deux identifiants, et rien n'empêche d'en écrire un couple incohérent à
         * la main. Le refus est explicite plutôt que d'afficher le catalogue d'un marché voisin
         * sous le nom d'un autre — une confusion qui se propagerait ensuite aux prix qu'on y règle.
         *
         * Le pays sert aussi au fil d'Ariane : sans lui, `/admin/catalogue/12` ne dirait pas si 12
         * désigne un pays ou une zone.
         */
        abort_unless($zone->country_id === $country->id, 404);

        $this->country = $country;
        $this->zone = $zone;
        $this->resetSectorForm();
    }

    /**
     * Ouvrir ou fermer un métier DANS CETTE ZONE.
     *
     * L'activation et le prix sont la même ligne. On ne supprime JAMAIS la ligne en éteignant :
     * rallumer doit retrouver le tarif saisi plutôt que de repartir de zéro, car ce tarif a pu
     * demander une négociation qu'on ne veut pas refaire.
     */
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
            /*
             * Première ouverture : on part du prix du métier.
             *
             * Ouvrir un métier dans une zone ne doit pas le mettre à zéro euro en attendant qu'on
             * saisisse une grille. Les champs deviendront éditables au lot suivant.
             */
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

    /**
     * Ouvrir ou fermer l'INTERVENTION IMMÉDIATE pour ce métier DANS CETTE ZONE.
     *
     * La décision est locale et c'est tout son intérêt : un plombier de garde à Bruxelles
     * n'implique pas un plombier de garde à Bastogne. Promettre l'immédiat là où personne n'est
     * jamais en ligne fait attendre le client devant sa porte pour rien, puis lui propose une
     * conversion en rendez-vous qu'il aurait choisie d'emblée si on la lui avait offerte.
     *
     * ELLE EXIGE QUE LE MÉTIER SOIT OUVERT. Un métier fermé dans la zone n'y est pas vendu du tout :
     * lui ouvrir l'immédiat produirait une ligne qui promet un dépannage pour un service absent du
     * parcours.
     */
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

    /**
     * RÉGLER LA MAJORATION DE CE MÉTIER DANS CETTE ZONE.
     *
     * Le multiplicateur vivait sur `trade_zone_pricing` sans qu'aucun écran ne le règle : le
     * moteur de surge le lisait, l'administration ne pouvait pas l'écrire. Une valeur qu'on
     * consulte sans pouvoir la changer est un réglage en lecture seule, c'est-à-dire pas un
     * réglage.
     *
     * IL SE RÈGLE SUR LA MÊME LIGNE que l'ouverture du métier et que l'immédiat, parce que c'est la
     * même décision commerciale : ce que je vends ici, à quel prix, et en urgence ou non. Les
     * séparer sur deux écrans a déjà produit deux tables concurrentes.
     *
     * LES BORNES SONT DURES. En dessous de 1, la « majoration » deviendrait une remise silencieuse
     * appliquée à tous ; au-dessus du plafond de configuration, elle dépasserait ce que le moteur
     * accepte de toute façon — autant refuser la saisie que promettre un réglage sans effet.
     */
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

    /**
     * Ouvre le formulaire de création, pour un secteur donné.
     *
     * Le secteur vient du bouton et non d'un champ : on clique « Ajouter un métier » DANS un
     * secteur, il n'y a donc rien à choisir de nouveau.
     */
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

    /**
     * Enregistre le métier, le rattache au secteur, et l'ouvre dans CETTE zone.
     *
     * LES TROIS GESTES SONT UN SEUL. C'est le détour qu'on supprime : il fallait créer le métier
     * sur un autre écran, revenir ici le rattacher — il arrivait orphelin — puis l'ouvrir. Trois
     * écrans pour ce que l'on venait manifestement faire.
     *
     * LE MÉTIER EST GLOBAL, SON OUVERTURE EST LOCALE. Il existera dans tous les pays et toutes les
     * zones ; seule la ligne `trade_zone_pricing` créée ici le rend disponible à Bruxelles. La
     * distinction est écrite dans le formulaire, faute de quoi on croirait créer un métier « pour
     * Bruxelles ».
     */
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

    #[Computed]
    public function sectors()
    {
        return Sector::query()
            ->ordered()
            ->with(['trades' => fn ($q) => $q->orderBy('sort_order')])
            ->get();
    }

    /**
     * Métiers actifs rattachés à aucun secteur.
     *
     * Ils restent utilisables par le reste de la plateforme mais n'apparaissent pas dans le
     * parcours de commande. Les taire ferait chercher longtemps pourquoi « Toiture » est
     * introuvable côté client.
     */
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

        /*
         * Sorti de la boucle, et lu en PROPRIÉTÉ.
         *
         * `#[Computed]` ne met en cache que l'accès propriété : `$this->tradesLosingClients()`
         * réexécute le corps à chaque appel, donc une fois par métier — soit exactement le coût
         * qui grandit avec la liste que cette analyse sert à débusquer.
         */
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
     * Sans ce signal, il faudrait ouvrir les douze métiers un par un pour découvrir lequel perd ses
     * clients — donc personne ne le découvrirait, et les statistiques ne serviraient à rien.
     *
     * LE VOLUME EST FILTRÉ D'ABORD, EN UNE REQUÊTE. Appeler l'analyse sur chaque métier du
     * catalogue reproduirait exactement le défaut qu'elle sert à débusquer : un coût qui grandit
     * avec le nombre de lignes affichées. On compte donc les commandes par métier d'un seul coup,
     * et on n'analyse que ceux qui en ont assez pour qu'un verdict veuille dire quelque chose —
     * en pratique, une poignée.
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

    /**
     * Le lecteur seul lit.
     *
     * `EnforcesAdminAccess` s'arrête à « est-ce un administrateur » : un `platform_role` à « admin »
     * assorti d'un `access_scope` à « readonly » franchit la garde et atteint cet écran, qui écrit
     * le catalogue et décide de l'ordre du carrousel.
     */
    private function refusesWrite(): bool
    {
        // La règle vit dans la Policy : on la consulte, on ne la redit pas.
        return ! Gate::allows('update', Trade::class);
    }

    /**
     * L'ordre des secteurs, tel que le navigateur l'a composé.
     *
     * C'est l'ordre du CARROUSEL : le premier secteur est celui que tout visiteur voit d'abord.
     * Il n'est pas cosmétique, et il est revalidé côté serveur — la liste vient du navigateur, elle
     * n'est pas crue sur parole.
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
     * C'est l'ordre du dock, donc le premier métier proposé une fois le secteur choisi. Il ne se
     * réglait pas du tout — ni à la souris, ni aux flèches — alors que ce sont les métiers qui se
     * vendent.
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

    /**
     * Les flèches, qui restent.
     *
     * Le glisser-déposer ne fonctionne ni au clavier ni avec un lecteur d'écran, et le catalogue
     * est un écran de travail quotidien.
     */
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
     * `null` si elle est partielle ou si elle contient un intrus. Les deux cas se soldent par un
     * refus, jamais par un réordonnancement de la partie reconnue.
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
            /*
             * LA CARTE DES MAJORATIONS (E28). Le multiplicateur se règle zone par zone, métier par
             * métier : personne ne pouvait voir ce que la plateforme facture en plus, PARTOUT, en
             * une fois. Une majoration oubliée à 2,5 dans une zone tourne indéfiniment, et se
             * découvre par une plainte.
             */
            'carteDesMajorations' => app(SurgeOverviewService::class)->carte(),
        ]);
    }
}
