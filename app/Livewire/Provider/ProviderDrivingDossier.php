<?php

namespace App\Livewire\Provider;

use App\Models\ProviderOnboardingDocument;
use App\Services\Onboarding\ProviderDocumentRequirements;
use App\Services\Onboarding\ProviderOnboardingService;
use App\Services\Onboarding\ProviderVehicleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * LE DOSSIER DE CONDUITE : permis, assurance du véhicule, carte grise, et la voiture elle-même.
 *
 * POURQUOI CET ÉCRAN EXISTE. Un prestataire ne pouvait déposer son permis NULLE PART. La table des
 * certifications de flotte connaît bien un type « permis de conduire », mais son champ de fichier
 * n'est écrit par aucun code et la seule voie de création est un administrateur via l'API : sur le
 * web comme sur le mobile, l'écran manquait tout simplement.
 *
 * ET IL DIT POURQUOI. L'angle mort connu de cette plateforme est le compte ACTIF mais jamais
 * VÉRIFIÉ : l'application s'ouvre normalement, et le téléphone cesse simplement de sonner. Ici, ce
 * qui manque est nommé, avec la date à laquelle ça deviendra bloquant — un prestataire qui sait
 * quoi faire le fait ; un prestataire qui attend appelle le support, puis part.
 */
class ProviderDrivingDossier extends Component
{
    use WithFileUploads;

    /** @var UploadedFile|null */
    public $fichier = null;

    public string $typeDocument = ProviderOnboardingDocument::TYPE_DRIVING_LICENSE;

    /** La date de validité, saisie au moment du dépôt — le seul instant où la pièce est en main. */
    public ?string $expiresAt = null;

    public string $plate = '';

    public string $brand = '';

    public string $model = '';

    public ?string $registeredAt = null;

    public ?string $message = null;

    public ?string $erreur = null;

    public function mount(): void
    {
        $vehicule = app(ProviderVehicleService::class)->vehiculeDe(Auth::user());

        if (! $vehicule) {
            return;
        }

        $this->plate = (string) $vehicule->plate;
        $this->brand = (string) ($vehicule->brand ?? '');
        $this->model = (string) ($vehicule->model ?? '');
        $this->registeredAt = $vehicule->registered_at?->toDateString();
    }

    /**
     * Les seules pièces que CET écran accepte.
     *
     * La liste est fermée volontairement : l'écran des justificatifs généraux traite le reste, et
     * laisser passer n'importe quel type ici permettrait de déposer une pièce d'identité sous
     * l'étiquette d'un permis — que la revue admin découvrirait, plusieurs jours plus tard.
     *
     * @return array<int, string>
     */
    public function typesAcceptes(): array
    {
        return [
            ProviderOnboardingDocument::TYPE_DRIVING_LICENSE,
            ProviderOnboardingDocument::TYPE_VEHICLE_INSURANCE,
            ProviderOnboardingDocument::TYPE_VEHICLE_REGISTRATION,
        ];
    }

    public function deposer(): void
    {
        $this->reset(['message', 'erreur']);

        $this->validate([
            'typeDocument' => ['required', 'in:'.implode(',', $this->typesAcceptes())],
            'fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            // Une pièce déjà périmée n'est pas un dossier : c'est un refus différé de trois jours.
            'expiresAt' => ['nullable', 'date', 'after:today'],
        ]);

        try {
            app(ProviderOnboardingService::class)->uploadDocument(
                Auth::user(),
                $this->typeDocument,
                $this->fichier,
                $this->expiresAt,
            );

            $this->fichier = null;
            $this->expiresAt = null;
            $this->message = 'Pièce envoyée. Elle sera relue sous peu.';
        } catch (\Throwable $e) {
            report($e);
            $this->erreur = "L'envoi a échoué. Réessayez, ou contactez le support si cela persiste.";
        }
    }

    public function enregistrerLeVehicule(): void
    {
        $this->reset(['message', 'erreur']);

        $this->validate([
            'plate' => ['required', 'string', 'max:24'],
            'brand' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:64'],
            // Pas dans le futur : une immatriculation à venir donnerait un âge négatif, donc
            // conforme, pour un véhicule qui n'est pas encore sur la route.
            'registeredAt' => ['required', 'date', 'before_or_equal:today'],
        ]);

        app(ProviderVehicleService::class)->declarer(Auth::user(), [
            'plate' => $this->plate,
            'brand' => $this->brand ?: null,
            'model' => $this->model ?: null,
            'registered_at' => $this->registeredAt,
        ]);

        $this->message = 'Véhicule enregistré.';
    }

    public function render(): View
    {
        $user = Auth::user();
        $exigences = app(ProviderDocumentRequirements::class);
        $vehicules = app(ProviderVehicleService::class);

        $deposees = ProviderOnboardingDocument::query()
            ->forUser($user->id)
            ->latest('id')
            ->get()
            ->unique('document_type');

        return view('livewire.provider.provider-driving-dossier', [
            'exigences' => collect($exigences->for($user))
                ->filter(fn (array $e) => in_array($e['type'], $this->typesAcceptes(), true))
                ->values(),
            'deposees' => $deposees->keyBy('document_type'),
            'dossierVehicule' => $vehicules->dossier($user),
            'metiersConcernes' => $vehicules->metiersConcernes($user),
        ]);
    }
}
