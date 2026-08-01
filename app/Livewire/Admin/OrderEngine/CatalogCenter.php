<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Models\Sector;
use App\Models\Trade;
use App\Services\OrderEngine\CatalogArchiver;
use App\Services\OrderEngine\QuestionnaireValidator;
use App\Services\OrderEngine\TradeFormPublisher;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
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
        $statuses = [];

        foreach ($this->sectors()->flatMap->trades as $trade) {
            $blocking = collect($validator->inspect($trade))
                ->where('severity', QuestionnaireValidator::SEVERITY_ERROR)
                ->count();

            $statuses[$trade->id] = [
                'blocking' => $blocking,
                'pending' => $publisher->hasUnpublishedChanges($trade),
                'version' => $publisher->currentRevision($trade)?->version,
            ];
        }

        return $statuses;
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

    public function moveSector(int $sectorId, int $direction): void
    {
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
