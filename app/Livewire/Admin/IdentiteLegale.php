<?php

namespace App\Livewire\Admin;

use App\Models\Parametre;
use App\Support\ActivityLogger;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * L'identité légale de la plateforme — celle qui s'affiche sur les mentions légales.
 *
 * Ces cinq valeurs étaient ÉCRITES EN DUR dans `resources/views/legal/mentions.blade.php`, sous
 * la forme « (à compléter) ». Une page légale publique qui annonce qu'elle n'est pas remplie est
 * un manquement, et personne ne pouvait la remplir sans toucher au code.
 */
class IdentiteLegale extends Component
{
    use EnforcesAdminAccess;

    /**
     * Les clefs de `parametres`, et ce qu'elles demandent.
     *
     * SANS POINT DANS LA CLEF : Livewire lirait `legal.societe` comme un chemin imbriqué, et la
     * validation chercherait une clef `societe` sous une clef `legal` qui n'existe pas.
     *
     * @var array<string, array{libelle: string, aide: string}>
     */
    public const CHAMPS = [
        'legal_societe' => [
            'libelle' => 'Forme juridique et numéro d’entreprise',
            'aide' => 'Par exemple : SRL Brio, BCE 0123.456.789',
        ],
        'legal_siege_social' => [
            'libelle' => 'Siège social',
            'aide' => 'L’adresse complète, telle qu’elle figure au registre.',
        ],
        'legal_email_contact' => [
            'libelle' => 'Email de contact',
            'aide' => 'Celui que le public peut écrire — pas une boîte interne.',
        ],
        'legal_directeur_publication' => [
            'libelle' => 'Directeur de la publication',
            'aide' => 'Le représentant légal, nommément.',
        ],
        'legal_hebergeur' => [
            'libelle' => 'Hébergeur',
            'aide' => 'Raison sociale et adresse — par exemple : OVH SAS, 2 rue Kellermann, 59100 Roubaix.',
        ],
    ];

    /** @var array<string, string> */
    public array $valeurs = [];

    // `/livewire/update` NE REJOUE AUCUN INTERMEDIAIRE DE ROUTE : la garde vit ici aussi.
    public function boot(): void
    {
        Gate::authorize('manage-platform');
    }

    public function mount(): void
    {
        foreach (array_keys(self::CHAMPS) as $cle) {
            $this->valeurs[$cle] = (string) Parametre::getValeur($cle, '');
        }
    }

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'valeurs.legal_societe' => ['nullable', 'string', 'max:255'],
            'valeurs.legal_siege_social' => ['nullable', 'string', 'max:255'],
            'valeurs.legal_email_contact' => ['nullable', 'email', 'max:255'],
            'valeurs.legal_directeur_publication' => ['nullable', 'string', 'max:255'],
            'valeurs.legal_hebergeur' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function enregistrer(): void
    {
        Gate::authorize('manage-platform');

        $this->validate();

        foreach (array_keys(self::CHAMPS) as $cle) {
            Parametre::setValeur($cle, trim((string) ($this->valeurs[$cle] ?? '')));
        }

        // UNE MENTION LEGALE EST OPPOSABLE : qui l'a changée, et quand, doit rester lisible.
        ActivityLogger::log('platform.legal_identity_updated', null, [
            'domain' => 'compliance',
            'champs_remplis' => count(array_filter($this->valeurs, fn ($v) => trim((string) $v) !== '')),
        ]);

        $this->dispatch('toast', 'Mentions légales enregistrées.', 'success');
    }

    /**
     * Les champs encore vides — la page publique les montrera comme « à compléter ».
     *
     * @return list<string>
     */
    public function manquants(): array
    {
        return array_values(array_filter(
            array_keys(self::CHAMPS),
            fn (string $cle) => trim((string) ($this->valeurs[$cle] ?? '')) === '',
        ));
    }

    public function render(): View
    {
        return view('livewire.admin.identite-legale');
    }
}
