<?php

namespace App\Livewire\ClientCompany;

use App\Models\OrganizationSite;
use App\Models\SigningAppointment;
use App\Services\Contracts\SigningAppointmentService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Planifier une signature de contrat sur place, et suivre celles à venir.
 *
 * Rien ne reliait jusqu'ici la signature électronique (`contract_signatures`) aux rendez-vous :
 * une société exigeant une signature en présence n'avait aucun moyen de la fixer.
 */
class SigningAppointments extends Component
{
    use EnforcesActiveOrgMembership;

    public ?int $siteId = null;

    public string $date = '';

    public string $heure = '10:00';

    public string $notes = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isClientCompany(), 403);

        $this->date = now()->addDays(3)->toDateString();
    }

    public function planifier(): void
    {
        $this->validate([
            'date' => ['required', 'date'],
            'heure' => ['required'],
        ]);

        $acteur = Auth::user();

        // Le service revérifie l'appartenance du local : on ne lui fait pas confiance sur parole.
        $site = $this->siteId
            ? OrganizationSite::find($this->siteId)
            : null;

        $rdv = app(SigningAppointmentService::class)->planifier(
            $acteur->currentOrganization,
            $acteur,
            Carbon::parse($this->date.' '.$this->heure),
            $site,
            null,
            $this->notes !== '' ? $this->notes : null,
        );

        if (! $rdv) {
            $this->addError('date', 'Rendez-vous impossible : vérifiez la date et le local choisis.');

            return;
        }

        $this->reset(['notes', 'siteId']);
    }

    public function marquerSigne(int $appointmentId): void
    {
        // Scopé sur l'organisation active : un identifiant forgé ne doit pas clore le rendez-vous
        // d'une autre société.
        $rdv = SigningAppointment::query()
            ->where('organization_account_id', Auth::user()->current_organization_id)
            ->find($appointmentId);

        if (! $rdv) {
            return;
        }

        app(SigningAppointmentService::class)->marquerSigne($rdv);
    }

    public function render(): View
    {
        $orgId = Auth::user()->current_organization_id;

        return view('livewire.client-company.signing-appointments', [
            'rendezVous' => SigningAppointment::query()
                ->where('organization_account_id', $orgId)
                ->with(['organizationSite:id,name,site_code', 'signer:id,name'])
                ->orderBy('scheduled_at')
                ->get(),
            'sites' => OrganizationSite::query()
                ->where('organization_account_id', $orgId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'site_code']),
        ])->layout('layouts.client-company');
    }
}
