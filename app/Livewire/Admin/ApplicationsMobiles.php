<?php

namespace App\Livewire\Admin;

use App\Models\Parametre;
use App\Models\TranslationOverride;
use App\Support\ActivityLogger;
use App\Support\Apps\LiensApplications;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Les liens de téléchargement des deux applications, et les accroches qui les accompagnent.
 *
 * Les liens vivent dans `parametres`. Les textes, eux, ne sont PAS recopiés ici : ils vivent dans
 * `translation_overrides`, exactement là où le centre de traductions les écrit déjà. Un second
 * magasin de textes aurait divergé du premier dès la première correction.
 */
class ApplicationsMobiles extends Component
{
    use EnforcesAdminAccess;

    /** Le groupe de traduction qui porte les accroches : les fichiers `vitrine.php` de `lang/`. */
    public const GROUPE_TEXTES = 'vitrine';

    /**
     * Les quatre accroches modifiables : clef PLATE -> clef de traduction, et libellé.
     *
     * PAS DE POINT DANS LA CLEF PLATE : Livewire lirait `textes.apps.client.titre` comme un
     * chemin imbriqué et chercherait `titre` sous `client` sous `apps`, qui n'existent pas.
     * Le champ resterait vide et la validation le déclarerait manquant.
     *
     * @var array<string, array{cle: string, libelle: string}>
     */
    public const TEXTES = [
        'client_titre' => ['cle' => 'apps.client.titre', 'libelle' => 'Titre de la carte client'],
        'client_accroche' => ['cle' => 'apps.client.accroche', 'libelle' => 'Accroche de la carte client'],
        'prestataire_titre' => ['cle' => 'apps.prestataire.titre', 'libelle' => 'Titre de la carte prestataire'],
        'prestataire_accroche' => ['cle' => 'apps.prestataire.accroche', 'libelle' => 'Accroche de la carte prestataire'],
    ];

    /** Ce que chaque destination demande à l'admin. */
    public const DESTINATIONS = [
        'ios' => ['libelle' => 'App Store (iOS)', 'aide' => 'https://apps.apple.com/…'],
        'android' => ['libelle' => 'Google Play (Android)', 'aide' => 'https://play.google.com/store/apps/…'],
        'smartlink' => ['libelle' => 'Lien unique', 'aide' => 'Redirige vers le bon magasin selon l’appareil. Prioritaire pour le QR.'],
    ];

    /** @var array<string, string> */
    public array $liens = [];

    public bool $clientVisible = true;

    public bool $prestataireVisible = true;

    public bool $qrActif = true;

    /** La langue dont on édite les accroches. */
    public string $locale = 'fr';

    /** @var array<string, string> */
    public array $textes = [];

    // `/livewire/update` NE REJOUE AUCUN INTERMEDIAIRE DE ROUTE : la garde vit ici aussi.
    public function boot(): void
    {
        Gate::authorize('manage-platform');
    }

    public function mount(): void
    {
        $this->locale = (string) app()->getLocale();

        if (! array_key_exists($this->locale, $this->localesEditables())) {
            $this->locale = 'fr';
        }

        foreach (LiensApplications::APPLICATIONS as $application) {
            foreach (array_keys(self::DESTINATIONS) as $destination) {
                $cle = LiensApplications::cleLien($application, $destination);
                $this->liens[$cle] = (string) Parametre::getValeur($cle, '');
            }
        }

        $this->clientVisible = LiensApplications::estVisible(LiensApplications::CLIENT);
        $this->prestataireVisible = LiensApplications::estVisible(LiensApplications::PRESTATAIRE);
        $this->qrActif = LiensApplications::qrActif();

        $this->chargerTextes();
    }

    /**
     * Les langues activées dans `config/i18n.php` — pas une liste écrite en dur ici.
     *
     * @return array<string, array{native_name?: string, flag?: string, enabled?: bool}>
     */
    public function localesEditables(): array
    {
        $locales = (array) config('i18n.locales', []);

        return array_filter(
            $locales,
            static fn (array $definition): bool => (bool) ($definition['enabled'] ?? false),
        );
    }

    /** Changer de langue recharge les accroches : l'écran n'en affiche jamais deux à la fois. */
    public function updatedLocale(string $valeur): void
    {
        if (! array_key_exists($valeur, $this->localesEditables())) {
            $this->locale = 'fr';
        }

        $this->chargerTextes();
    }

    protected function chargerTextes(): void
    {
        foreach (self::TEXTES as $plate => $champ) {
            // `trans()` traverse deja le loader : on lit la valeur EFFECTIVE, override compris.
            $this->textes[$plate] = (string) trans(self::GROUPE_TEXTES.'.'.$champ['cle'], [], $this->locale);
        }
    }

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        $regles = [
            'clientVisible' => ['boolean'],
            'prestataireVisible' => ['boolean'],
            'qrActif' => ['boolean'],
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys($this->localesEditables()))],
        ];

        foreach (LiensApplications::APPLICATIONS as $application) {
            foreach (array_keys(self::DESTINATIONS) as $destination) {
                $cle = LiensApplications::cleLien($application, $destination);

                // `https` SEUL : un magasin d'applications n'a jamais d'URL en clair, et un
                // schema libre laisserait poser un `javascript:` dans un href public.
                $regles['liens.'.$cle] = ['nullable', 'string', 'max:500', 'url:https'];
            }
        }

        foreach (array_keys(self::TEXTES) as $plate) {
            $regles['textes.'.$plate] = ['required', 'string', 'max:300'];
        }

        return $regles;
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        $libelles = [];

        foreach (LiensApplications::APPLICATIONS as $application) {
            foreach (self::DESTINATIONS as $destination => $definition) {
                $libelles['liens.'.LiensApplications::cleLien($application, $destination)]
                    = $definition['libelle'].' — '.$application;
            }
        }

        foreach (self::TEXTES as $plate => $champ) {
            $libelles['textes.'.$plate] = $champ['libelle'];
        }

        return $libelles;
    }

    public function enregistrer(): void
    {
        Gate::authorize('manage-platform');

        $this->validate();

        foreach ($this->liens as $cle => $valeur) {
            Parametre::setValeur($cle, trim($valeur));
        }

        Parametre::setValeur(LiensApplications::cleVisibilite(LiensApplications::CLIENT), $this->clientVisible ? '1' : '0');
        Parametre::setValeur(LiensApplications::cleVisibilite(LiensApplications::PRESTATAIRE), $this->prestataireVisible ? '1' : '0');
        Parametre::setValeur(LiensApplications::cleQr(), $this->qrActif ? '1' : '0');

        foreach (self::TEXTES as $plate => $champ) {
            $valeur = (string) ($this->textes[$plate] ?? '');

            TranslationOverride::updateOrCreate(
                [
                    'locale' => $this->locale,
                    'group' => self::GROUPE_TEXTES,
                    'key' => $champ['cle'],
                    'namespace' => '*',
                ],
                [
                    'value' => trim($valeur),
                    'is_published' => true,
                    'updated_by_user_id' => Auth::id(),
                ],
            );
        }

        // Un lien de telechargement est public : qui l'a change, et quand, doit rester lisible.
        ActivityLogger::log('platform.mobile_apps_updated', null, [
            'domain' => 'plateforme',
            'locale_textes' => $this->locale,
            'liens_publies' => count($this->apercu()['client']['liens']) + count($this->apercu()['prestataire']['liens']),
        ]);

        $this->dispatch('toast', 'Applications mobiles enregistrées.', 'success');
    }

    /**
     * Ce que le public verra réellement, calculé par le même code que le gabarit.
     *
     * L'admin ne devine pas : il lit ici la liste exacte des boutons qui s'afficheront.
     *
     * @return array<string, array{visible: bool, liens: array<string, string>}>
     */
    public function apercu(): array
    {
        return [
            LiensApplications::CLIENT => LiensApplications::pour(LiensApplications::CLIENT),
            LiensApplications::PRESTATAIRE => LiensApplications::pour(LiensApplications::PRESTATAIRE),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.applications-mobiles');
    }
}
