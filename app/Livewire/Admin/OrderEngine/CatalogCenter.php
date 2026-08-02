<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Models\Sector;
use App\Models\Trade;
use App\Services\OrderEngine\CatalogArchiver;
use App\Services\OrderEngine\QuestionInsights;
use App\Services\OrderEngine\QuestionnaireValidator;
use App\Services\OrderEngine\TradeFormPublisher;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
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
 */
#[Layout('layouts.app')]
class CatalogCenter extends Component
{
    /** Même seuil que `QuestionInsights::worstOffenders()` : en deçà, aucun verdict. */
    private const MINIMUM_ORDERS_TO_CONCLUDE = 20;

    /** Le refus vaut au niveau du composant, pas seulement de la route. */
    use EnforcesAdminAccess;

    public ?int $editingSectorId = null;

    /** @var array<string, mixed> */
    public array $sectorForm = [];

    public ?int $archivingSectorId = null;

    /** @var array<string, mixed>|null */
    public ?array $archiveImpact = null;

    public ?string $flash = null;

    /** Les métiers sans secteur n'apparaissent pas dans le carrousel : on ne les cache pas pour autant. */
    public bool $showOrphanTrades = false;

    public function mount(): void
    {
        $this->resetSectorForm();
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
        $user = Auth::user();

        return $user !== null
            && method_exists($user, 'isReadOnlyAdmin')
            && $user->isReadOnlyAdmin();
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
        return view('livewire.admin.order-engine.catalog-center');
    }
}
