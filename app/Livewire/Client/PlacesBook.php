<?php

namespace App\Livewire\Client;

use App\Models\ClientPlace;
use App\Services\Client\ClientPlaceService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LE CARNET DE LIEUX (E2).
 *
 * UN CLIENT A PLUSIEURS LIEUX, et la plateforme n'en connaissait qu'un. Quelqu'un qui fait nettoyer
 * son appartement et la maison de sa mère retape l'adresse, l'étage et le code à chaque commande —
 * et se trompe une fois sur cinq, ce qui envoie un prestataire à la mauvaise porte.
 *
 * CE QUI COMPTE N'EST PAS L'ADRESSE, ce sont les CONSIGNES : l'étage, le digicode, la clé chez la
 * voisine, le chien, l'allergie aux produits chlorés. Elles se redonnaient oralement à chaque
 * nouveau prestataire, ou se perdaient. Cet écran les enregistre une fois ; la fiche d'accès sur
 * place les révèle à l'arrivée, et seulement là.
 *
 * ON ARCHIVE, ON NE SUPPRIME PAS. Les missions passées portent ce lieu : l'effacer viderait
 * l'historique de ses adresses, et personne ne saurait plus où une intervention a eu lieu.
 */
class PlacesBook extends Component
{
    public string $libelle = '';

    public string $adresse = '';

    public string $ville = '';

    public string $codePostal = '';

    public string $etage = '';

    public string $consignes = '';

    public bool $alarme = false;

    public string $produits = '';

    public string $allergies = '';

    public string $animaux = '';

    /** Le lieu en cours de modification, ou `null` pour une création. */
    #[Locked]
    public ?int $lieuEnEditionId = null;

    #[Locked]
    public ?string $refus = null;

    public function enregistrer(): void
    {
        $this->validate([
            'libelle' => ['required', 'string', 'max:80'],
            'adresse' => ['required', 'string', 'max:255'],
        ]);

        $attributs = [
            'label' => $this->libelle,
            'address' => $this->adresse,
            'city' => $this->ville ?: null,
            'postal_code' => $this->codePostal ?: null,
            'floor' => $this->etage ?: null,
            'access_instructions' => $this->consignes ?: null,
            'alarm_code_required' => $this->alarme,
            'preferences' => array_filter([
                'products' => $this->produits ?: null,
                'allergies' => $this->allergies ?: null,
                'pets' => $this->animaux ?: null,
            ], fn ($valeur) => $valeur !== null),
        ];

        $service = app(ClientPlaceService::class);

        try {
            if ($this->lieuEnEditionId !== null) {
                $lieu = $service->lieuDuClient(Auth::user(), $this->lieuEnEditionId);

                if ($lieu !== null) {
                    $service->modifier($lieu, $attributs);
                }
            } else {
                $service->enregistrer(Auth::user(), $attributs);
            }

            $this->reinitialiser();
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    public function modifier(int $lieuId): void
    {
        // Le scoping fait partie de la requête : un lieu porte l'adresse, l'étage et le code
        // d'alarme du domicile de quelqu'un.
        $lieu = app(ClientPlaceService::class)->lieuDuClient(Auth::user(), $lieuId);

        if ($lieu === null) {
            return;
        }

        $preferences = $lieu->preferencesLisibles();

        $this->lieuEnEditionId = $lieu->id;
        $this->libelle = $lieu->label;
        $this->adresse = $lieu->address;
        $this->ville = (string) ($lieu->city ?? '');
        $this->codePostal = (string) ($lieu->postal_code ?? '');
        $this->etage = (string) ($lieu->floor ?? '');
        $this->consignes = (string) ($lieu->access_instructions ?? '');
        $this->alarme = (bool) $lieu->alarm_code_required;
        $this->produits = (string) ($preferences['products'] ?? '');
        $this->allergies = (string) ($preferences['allergies'] ?? '');
        $this->animaux = (string) ($preferences['pets'] ?? '');
    }

    public function definirParDefaut(int $lieuId): void
    {
        $lieu = app(ClientPlaceService::class)->lieuDuClient(Auth::user(), $lieuId);

        if ($lieu !== null) {
            app(ClientPlaceService::class)->definirParDefaut($lieu);
        }
    }

    public function archiver(int $lieuId): void
    {
        $lieu = app(ClientPlaceService::class)->lieuDuClient(Auth::user(), $lieuId);

        if ($lieu !== null) {
            app(ClientPlaceService::class)->archiver($lieu);
        }
    }

    public function annulerLEdition(): void
    {
        $this->reinitialiser();
    }

    private function reinitialiser(): void
    {
        $this->reset([
            'libelle', 'adresse', 'ville', 'codePostal', 'etage', 'consignes',
            'produits', 'allergies', 'animaux', 'refus',
        ]);
        $this->alarme = false;
        $this->lieuEnEditionId = null;
    }

    public function render(): View
    {
        return view('livewire.client.places-book', [
            'lieux' => app(ClientPlaceService::class)->pour(Auth::user()),
            'archives' => ClientPlace::query()
                ->where('user_id', Auth::id())
                ->whereNotNull('archived_at')
                ->orderBy('label')
                ->get(),
            'maximum' => ClientPlaceService::MAXIMUM_LIEUX,
        ])->layout('layouts.app');
    }
}
