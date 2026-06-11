<?php

namespace App\Livewire\ClientCompany;

use App\Models\Mission;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Espace société B2B P3 — photos avant/après d'une intervention pour le
 * gestionnaire. Scopé strictement à l'org (anti-IDOR) : la mission doit
 * appartenir à un booking de l'organisation active.
 */
class SiteMissionPhotos extends Component
{
    use EnforcesActiveOrgMembership;

    public Mission $mission;

    public function mount(Mission $mission): void
    {
        $orgId = Auth::user()->current_organization_id;

        abort_unless(
            (int) $mission->booking?->customer_organization_id === (int) $orgId,
            403,
            "Cette intervention n'appartient pas à votre organisation.",
        );

        $this->mission = $mission->load([
            'media.uploadedBy:id,name',
            'booking:id,booking_reference',
            'leadEmployee:id,name',
        ]);
    }

    public function render(): View
    {
        $beforePhotos = $this->mission->media->where('media_type', 'before_photo')->values();
        $afterPhotos = $this->mission->media->where('media_type', 'after_photo')->values();

        return view('livewire.client-company.site-mission-photos', [
            'beforePhotos' => $beforePhotos,
            'afterPhotos' => $afterPhotos,
        ])->layout('layouts.client-company');
    }
}
