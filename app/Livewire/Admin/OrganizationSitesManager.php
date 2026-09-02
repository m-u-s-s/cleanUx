<?php

namespace App\Livewire\Admin;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * LES SITES DE TOUTES LES SOCIETES, VUS PAR L'ADMINISTRATION.
 *
 * L'ecran filtrait sur `Auth::user()->organization_account_id` — l'organisation de L'ADMINISTRATEUR,
 * qui n'en a pas. La liste etait donc vide en permanence pendant que sept sites existaient, et le
 * bouton d'ajout ecrivait un identifiant nul dans une colonne NOT NULL.
 *
 * L'administrateur n'appartient a aucune societe : il les gere TOUTES. La societe devient un champ
 * du formulaire, et un filtre de la liste.
 */
class OrganizationSitesManager extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    // ── Filtres ────────────────────────────────────────────────────────────
    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'societe', except: '')]
    public string $organisationId = '';

    #[Url(as: 'zone', except: '')]
    public string $zoneId = '';

    #[Url(as: 'statut', except: '')]
    public string $statut = '';

    #[Url(as: 'pays', except: '')]
    public string $pays = '';

    #[Url(as: 'contrainte', except: '')]
    public string $contrainte = '';

    // ── Formulaire ─────────────────────────────────────────────────────────
    public bool $formulaireOuvert = false;

    #[Locked]
    public ?int $siteEnCours = null;

    #[Locked]
    public ?int $siteASupprimer = null;

    public string $organisation = '';

    public string $nom = '';

    public string $type = '';

    public string $adresse = '';

    public string $codePostal = '';

    public string $ville = '';

    public string $paysDuSite = 'BE';

    public string $zoneDeService = '';

    public ?int $surface = null;

    public string $contactNom = '';

    public string $contactTelephone = '';

    public string $contactCourriel = '';

    public string $instructionsAcces = '';

    public bool $parking = false;

    public bool $badge = false;

    public bool $alarme = false;

    public bool $zonesSensibles = false;

    public string $frequence = '';

    public string $creneauPrefere = '';

    public string $prestatairePrefere = '';

    public string $statutDuSite = 'active';

    public bool $principal = false;

    public bool $actif = true;

    public string $remarques = '';

    /**
     * LA CAPACITE EN PLUS DU ROLE.
     *
     * `EnforcesAdminAccess` ne verifie que « est-ce un administrateur ». La capacite
     * `manage-entreprises` est declaree par le module et posee par `module_gate` — mais
     * `/livewire/update` ne rejoue AUCUN middleware de route. Sans cette garde, tout
     * administrateur pouvait creer ou supprimer un site par un simple appel de composant.
     */
    public function boot(): void
    {
        Gate::authorize('manage-entreprises');
    }

    public function updated(string $champ, mixed $valeur = null): void
    {
        if (in_array($champ, ['recherche', 'organisationId', 'zoneId', 'statut', 'pays', 'contrainte'], true)) {
            $this->resetPage();
        }
    }

    public function reinitialiserLesFiltres(): void
    {
        $this->reset(['recherche', 'organisationId', 'zoneId', 'statut', 'pays', 'contrainte']);
        $this->resetPage();
    }

    // ── Listes de reference ────────────────────────────────────────────────

    /** @return Collection<int, OrganizationAccount> */
    #[Computed]
    public function organisations(): Collection
    {
        return OrganizationAccount::query()->orderBy('name')->get(['id', 'name', 'type']);
    }

    /** @return Collection<int, ServiceZone> */
    #[Computed]
    public function zones(): Collection
    {
        return ServiceZone::query()->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function prestataires(): Collection
    {
        return User::query()->providers()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /** @return array<string, string> */
    #[Computed]
    public function paysDisponibles(): array
    {
        return OrganizationSite::query()
            ->whereNotNull('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country', 'country')
            ->all();
    }

    /**
     * Ce que la liste filtree represente, en un coup d'oeil.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function reperes(): array
    {
        $base = $this->requeteFiltree();

        return [
            'sites' => (clone $base)->count(),
            'societes' => (clone $base)->distinct()->count('organization_account_id'),
            'zones' => (clone $base)->whereNotNull('service_zone_id')->distinct()->count('service_zone_id'),
            'contraintes' => (clone $base)->where(function (Builder $q) {
                $q->where('badge_required', true)
                    ->orWhere('alarm_code_required', true)
                    ->orWhere('has_sensitive_areas', true);
            })->count(),
        ];
    }

    // ── Formulaire ─────────────────────────────────────────────────────────

    public function ouvrirCreation(): void
    {
        $this->reinitialiserLeFormulaire();
        $this->organisation = $this->organisationId;
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(int $siteId): void
    {
        $site = OrganizationSite::findOrFail($siteId);

        $this->siteEnCours = $site->id;
        $this->organisation = (string) $site->organization_account_id;
        $this->nom = (string) $site->name;
        $this->type = (string) $site->type;
        $this->adresse = (string) $site->address;
        $this->codePostal = (string) $site->postal_code;
        $this->ville = (string) $site->city;
        $this->paysDuSite = (string) ($site->country ?: 'BE');
        $this->zoneDeService = (string) $site->service_zone_id;
        $this->surface = $site->surface_m2;
        $this->contactNom = (string) $site->contact_name;
        $this->contactTelephone = (string) $site->contact_phone;
        $this->contactCourriel = (string) $site->contact_email;
        $this->instructionsAcces = (string) $site->access_instructions;
        $this->parking = (bool) $site->parking_available;
        $this->badge = (bool) $site->badge_required;
        $this->alarme = (bool) $site->alarm_code_required;
        $this->zonesSensibles = (bool) $site->has_sensitive_areas;
        $this->frequence = (string) $site->cleaning_frequency;
        $this->creneauPrefere = (string) $site->preferred_time_slot;
        $this->prestatairePrefere = (string) $site->preferred_provider_id;
        $this->statutDuSite = (string) ($site->status ?: 'active');
        $this->principal = (bool) $site->is_primary;
        $this->actif = (bool) $site->is_active;
        $this->remarques = (string) $site->notes;

        $this->formulaireOuvert = true;
    }

    public function fermerLeFormulaire(): void
    {
        $this->formulaireOuvert = false;
        $this->reinitialiserLeFormulaire();
    }

    /** Remet le formulaire a neuf, sans toucher aux filtres de la liste. */
    private function reinitialiserLeFormulaire(): void
    {
        $this->reset([
            'siteEnCours', 'organisation', 'nom', 'type', 'adresse', 'codePostal', 'ville',
            'paysDuSite', 'zoneDeService', 'surface', 'contactNom', 'contactTelephone',
            'contactCourriel', 'instructionsAcces', 'parking', 'badge', 'alarme',
            'zonesSensibles', 'frequence', 'creneauPrefere', 'prestatairePrefere',
            'statutDuSite', 'principal', 'actif', 'remarques',
        ]);

        $this->resetValidation();
    }

    public function enregistrer(): void
    {
        $donnees = $this->validate([
            'organisation' => ['required', 'integer', 'exists:organization_accounts,id'],
            'nom' => ['required', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:60'],
            'adresse' => ['required', 'string', 'max:255'],
            'codePostal' => ['required', 'string', 'max:20'],
            'ville' => ['required', 'string', 'max:120'],
            'paysDuSite' => ['required', 'string', 'size:2'],
            'zoneDeService' => ['nullable', 'integer', 'exists:service_zones,id'],
            'surface' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'contactNom' => ['nullable', 'string', 'max:150'],
            'contactTelephone' => ['nullable', 'string', 'max:40'],
            'contactCourriel' => ['nullable', 'email', 'max:190'],
            'instructionsAcces' => ['nullable', 'string', 'max:2000'],
            'frequence' => ['nullable', 'in:one_time,weekly,biweekly,monthly'],
            'creneauPrefere' => ['nullable', 'string', 'max:60'],
            'prestatairePrefere' => ['nullable', 'integer', 'exists:users,id'],
            'statutDuSite' => ['required', 'in:active,archived'],
            'remarques' => ['nullable', 'string', 'max:2000'],
        ]);

        $charge = [
            'organization_account_id' => (int) $donnees['organisation'],
            'name' => $donnees['nom'],
            'type' => $donnees['type'] ?: null,
            'address' => $donnees['adresse'],
            'postal_code' => $donnees['codePostal'],
            'city' => $donnees['ville'],
            'country' => strtoupper($donnees['paysDuSite']),
            'service_zone_id' => $donnees['zoneDeService'] ?: null,
            'surface_m2' => $donnees['surface'],
            'contact_name' => $donnees['contactNom'] ?: null,
            'contact_phone' => $donnees['contactTelephone'] ?: null,
            'contact_email' => $donnees['contactCourriel'] ?: null,
            'access_instructions' => $donnees['instructionsAcces'] ?: null,
            'parking_available' => $this->parking,
            'badge_required' => $this->badge,
            'alarm_code_required' => $this->alarme,
            'has_sensitive_areas' => $this->zonesSensibles,
            // LA COLONNE VIVANTE EST `cleaning_frequency`. Le commentaire du modele annonce un
            // renommage vers `service_frequency` qui n'a jamais eu lieu : elle est vide sur 7 sites.
            'cleaning_frequency' => $donnees['frequence'] ?: null,
            'preferred_time_slot' => $donnees['creneauPrefere'] ?: null,
            'preferred_provider_id' => $donnees['prestatairePrefere'] ?: null,
            'status' => $donnees['statutDuSite'],
            'is_primary' => $this->principal,
            'is_active' => $this->actif,
            'notes' => $donnees['remarques'] ?: null,
        ];

        if ($this->siteEnCours !== null) {
            OrganizationSite::findOrFail($this->siteEnCours)->update($charge);
            $this->dispatch('toast', 'Site mis à jour', 'success');
        } else {
            OrganizationSite::create($charge);
            $this->dispatch('toast', 'Site ajouté', 'success');
        }

        $this->fermerLeFormulaire();
    }

    public function demanderLaSuppression(int $siteId): void
    {
        $this->siteASupprimer = $siteId;
    }

    public function annulerLaSuppression(): void
    {
        $this->siteASupprimer = null;
    }

    /**
     * ARCHIVER PLUTOT QUE DETRUIRE. Les reservations pointent vers le site : l'effacer casserait
     * leur historique. L'ecran client archive deja de cette maniere.
     */
    public function archiver(): void
    {
        if ($this->siteASupprimer === null) {
            return;
        }

        OrganizationSite::findOrFail($this->siteASupprimer)
            ->update(['status' => 'archived', 'is_active' => false]);

        $this->siteASupprimer = null;
        $this->dispatch('toast', 'Site archivé', 'success');
    }

    public function reactiver(int $siteId): void
    {
        OrganizationSite::findOrFail($siteId)->update(['status' => 'active', 'is_active' => true]);

        $this->dispatch('toast', 'Site réactivé', 'success');
    }

    // ── Requete ────────────────────────────────────────────────────────────

    /** @return Builder<OrganizationSite> */
    private function requeteFiltree(): Builder
    {
        return OrganizationSite::query()
            ->when($this->recherche !== '', function (Builder $q) {
                $terme = '%'.$this->recherche.'%';
                $q->where(function (Builder $sous) use ($terme) {
                    $sous->where('name', 'like', $terme)
                        ->orWhere('address', 'like', $terme)
                        ->orWhere('city', 'like', $terme)
                        ->orWhere('postal_code', 'like', $terme)
                        ->orWhere('contact_name', 'like', $terme);
                });
            })
            ->when($this->organisationId !== '', fn (Builder $q) => $q->where('organization_account_id', $this->organisationId))
            ->when($this->zoneId !== '', fn (Builder $q) => $q->where('service_zone_id', $this->zoneId))
            ->when($this->statut !== '', fn (Builder $q) => $q->where('status', $this->statut))
            ->when($this->pays !== '', fn (Builder $q) => $q->where('country', $this->pays))
            ->when($this->contrainte !== '', function (Builder $q) {
                match ($this->contrainte) {
                    'badge' => $q->where('badge_required', true),
                    'alarme' => $q->where('alarm_code_required', true),
                    'sensible' => $q->where('has_sensitive_areas', true),
                    'parking' => $q->where('parking_available', true),
                    default => null,
                };
            });
    }

    public function render()
    {
        return view('livewire.admin.organization-sites-manager', [
            'sites' => $this->requeteFiltree()
                ->with(['organizationAccount:id,name', 'serviceZone:id,name'])
                ->withCount('bookings')
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }
}
