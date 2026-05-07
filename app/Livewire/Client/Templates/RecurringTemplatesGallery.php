<?php

namespace App\Livewire\Client\Templates;

use App\Models\OrganizationAccount;
use App\Models\RecurringTemplate;
use App\Services\Client\Templates\ApplyRecurringTemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Phase 6.1 — Galerie de templates de récurrence pré-définis.
 *
 * Affiche les templates système + ceux du user, groupés par catégorie.
 * Au clic sur un template → modal de confirmation (site, date début, fin).
 * Au confirm → crée une RecurringBookingSeries et redirige vers /recurrences.
 */
class RecurringTemplatesGallery extends Component
{
    public string $selectedCategory = 'all';

    /** Modal d'application */
    public ?int $applyTemplateId = null;
    public ?int $selectedSiteId  = null;
    public string $applyStartsAt = '';
    public ?string $applyEndsAt = null;
    public ?int $applyOccurrenceCount = null;
    public ?string $applyCustomTime = null;

    /** Flash */
    public ?string $flashMessage = null;
    public ?string $flashType = null;

    public function mount(): void
    {
        $this->applyStartsAt = now()->addDays(2)->toDateString();
    }

    public function setCategory(string $category): void
    {
        $this->selectedCategory = $category;
    }

    public function openApplyModal(int $templateId): void
    {
        $this->applyTemplateId = $templateId;
        $template = RecurringTemplate::find($templateId);
        if ($template?->default_time) {
            $this->applyCustomTime = $template->default_time->format('H:i');
        }
    }

    public function closeApplyModal(): void
    {
        $this->applyTemplateId = null;
        $this->selectedSiteId = null;
        $this->applyEndsAt = null;
        $this->applyOccurrenceCount = null;
        $this->applyCustomTime = null;
    }

    public function applyTemplate(): void
    {
        if (! $this->applyTemplateId) return;

        $template = RecurringTemplate::find($this->applyTemplateId);
        if (! $template) {
            $this->flash('Template introuvable.', 'error');
            return;
        }

        $user = Auth::user();

        try {
            $series = app(ApplyRecurringTemplateService::class)->apply(
                $user,
                $template,
                [
                    'organization_site_id' => $this->selectedSiteId,
                    'starts_at'            => $this->applyStartsAt,
                    'ends_at'              => $this->applyEndsAt,
                    'occurrence_count'     => $this->applyOccurrenceCount,
                    'custom_time'          => $this->applyCustomTime,
                ]
            );

            $this->flash(
                "Récurrence \"{$template->name}\" créée. Tu peux la consulter dans Mes récurrences.",
                'success'
            );
            $this->closeApplyModal();

            // Redirige vers la liste des récurrences
            if (\Illuminate\Support\Facades\Route::has('client.recurring.index')) {
                $this->redirect(route('client.recurring.index'), navigate: true);
            }
        } catch (\DomainException $e) {
            $this->flash($e->getMessage(), 'error');
        } catch (\Throwable $e) {
            report($e);
            $this->flash('Une erreur est survenue.', 'error');
        }
    }

    public function clearFlash(): void
    {
        $this->flashMessage = null;
        $this->flashType = null;
    }

    private function flash(string $msg, string $type): void
    {
        $this->flashMessage = $msg;
        $this->flashType = $type;
    }

    public function render(): View
    {
        $user = Auth::user();

        $templates = RecurringTemplate::query()
            ->active()
            ->forUser($user->id, $user->organization_account_id)
            ->ordered()
            ->get();

        if ($this->selectedCategory !== 'all') {
            $templates = $templates->where('category', $this->selectedCategory);
        }

        // Sites disponibles pour le user (entreprise)
        $sites = collect();
        if ($user->organization_account_id) {
            $sites = OrganizationAccount::find($user->organization_account_id)
                ?->sites()
                ->orderBy('name')
                ->get(['id', 'name', 'city'])
                ?? collect();
        }

        // Catégories avec compteurs
        $allTemplates = RecurringTemplate::query()
            ->active()
            ->forUser($user->id, $user->organization_account_id)
            ->get();

        $categories = [
            ['value' => 'all',         'label' => 'Tous',         'count' => $allTemplates->count()],
            ['value' => 'office',      'label' => 'Bureaux',      'count' => $allTemplates->where('category', 'office')->count()],
            ['value' => 'retail',      'label' => 'Commerces',    'count' => $allTemplates->where('category', 'retail')->count()],
            ['value' => 'hospitality', 'label' => 'Hôtellerie',   'count' => $allTemplates->where('category', 'hospitality')->count()],
            ['value' => 'residential', 'label' => 'Résidentiel',  'count' => $allTemplates->where('category', 'residential')->count()],
            ['value' => 'other',       'label' => 'Autre',        'count' => $allTemplates->where('category', 'other')->count()],
        ];

        $applyingTemplate = $this->applyTemplateId
            ? RecurringTemplate::find($this->applyTemplateId)
            : null;

        return view('livewire.client.templates.recurring-templates-gallery', [
            'templates'        => $templates,
            'categories'       => $categories,
            'sites'            => $sites,
            'applyingTemplate' => $applyingTemplate,
            'isCompany'        => (bool) $user->organization_account_id,
        ]);
    }
}
