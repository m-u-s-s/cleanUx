<?php

namespace App\Livewire\Admin;

use App\Models\EmailTemplate;
use App\Models\EmailTheme;
use App\Services\Email\MoteurDeThemeEmail;
use App\Services\Email\RenduDeBlocsEmail;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * L'HABILLAGE DES E-MAILS, ET SON CALENDRIER.
 *
 * Un thème porte le logo, les images, les neuf couleurs et la typographie. Il s'applique par
 * FENÊTRE DE DATES : Black Friday du 24 au 30 novembre, Noël du 20 décembre au 2 janvier — et le
 * moteur choisit tout seul, sans qu'un gabarit soit touché.
 *
 * L'aperçu montre un vrai gabarit sous le thème en cours d'édition : on voit ce qui partira, au
 * mois de mai comme en décembre.
 *
 * @property-read Collection<int, EmailTheme> $themes
 * @property-read ?EmailTheme $themeCourant
 * @property-read list<array<string, mixed>> $calendrier
 */
class EmailThemesStudio extends Component
{
    use EnforcesAdminAccess;

    #[Locked]
    public ?int $themeOuvert = null;

    #[Locked]
    public ?int $themeASupprimer = null;

    public string $nom = '';

    public string $codeDuTheme = '';

    public string $descriptionDuTheme = '';

    public bool $actif = true;

    public bool $parDefaut = false;

    public string $debut = '';

    public string $fin = '';

    public bool $annuel = false;

    public int $priorite = 10;

    public string $logo = '';

    public string $imageEntete = '';

    public string $imageFond = '';

    public string $pied = '';

    public string $police = 'Arial, Helvetica, sans-serif';

    public int $rayon = 20;

    /** @var array<string, string> */
    public array $couleurs = [];

    /** Le gabarit servant de modèle à l'aperçu. */
    public string $gabaritDApercu = '';

    /** Les neuf couleurs d'un thème, avec ce que chacune peint. */
    public const COULEURS = [
        'color_accent' => 'Accent',
        'color_accent_contrast' => 'Texte sur accent',
        'color_page_background' => 'Fond de page',
        'color_card_background' => 'Fond de la carte',
        'color_text' => 'Texte',
        'color_text_muted' => 'Texte secondaire',
        'color_border' => 'Bordures',
        'color_banner_from' => 'Bandeau — début',
        'color_banner_to' => 'Bandeau — fin',
    ];

    public function boot(): void
    {
        Gate::authorize('manage-communication');
    }

    public function mount(): void
    {
        $premier = $this->themes->first();

        if ($premier instanceof EmailTheme) {
            $this->ouvrir((int) $premier->id);
        }

        $this->gabaritDApercu = (string) (EmailTemplate::query()->value('code') ?? '');
    }

    public function ouvrir(int $id): void
    {
        $theme = EmailTheme::query()->find($id);

        if (! $theme instanceof EmailTheme) {
            return;
        }

        $this->themeOuvert = (int) $theme->id;
        $this->nom = (string) $theme->name;
        $this->codeDuTheme = (string) $theme->code;
        $this->descriptionDuTheme = (string) $theme->description;
        $this->actif = (bool) $theme->is_active;
        $this->parDefaut = (bool) $theme->is_default;
        $this->debut = $theme->starts_on?->format('Y-m-d') ?? '';
        $this->fin = $theme->ends_on?->format('Y-m-d') ?? '';
        $this->annuel = (bool) $theme->recurs_yearly;
        $this->priorite = (int) $theme->priority;
        $this->logo = (string) $theme->logo_url;
        $this->imageEntete = (string) $theme->header_image_url;
        $this->imageFond = (string) $theme->background_image_url;
        $this->pied = (string) $theme->footer_text;
        $this->police = (string) $theme->font_stack;
        $this->rayon = (int) $theme->corner_radius;

        $this->couleurs = [];
        foreach (array_keys(self::COULEURS) as $champ) {
            $this->couleurs[$champ] = (string) $theme->{$champ};
        }

        $this->resetValidation();
    }

    public function enregistrer(): void
    {
        $this->validate($this->regles());

        $theme = EmailTheme::query()->find($this->themeOuvert);

        if (! $theme instanceof EmailTheme) {
            return;
        }

        // UN SEUL THEME PAR DEFAUT. Sans cette remise a zero, deux « par defaut » coexisteraient
        // et le moteur en choisirait un par son identifiant — c'est-a-dire au hasard.
        if ($this->parDefaut) {
            EmailTheme::query()->whereKeyNot($theme->id)->update(['is_default' => false]);
        }

        $theme->update([
            'name' => $this->nom,
            'description' => $this->descriptionDuTheme ?: null,
            'is_active' => $this->actif,
            'is_default' => $this->parDefaut,
            'starts_on' => $this->debut ?: null,
            'ends_on' => $this->fin ?: null,
            'recurs_yearly' => $this->annuel,
            'priority' => $this->priorite,
            'logo_url' => $this->logo ?: null,
            'header_image_url' => $this->imageEntete ?: null,
            'background_image_url' => $this->imageFond ?: null,
            'footer_text' => $this->pied ?: null,
            'font_stack' => $this->police,
            'corner_radius' => $this->rayon,
            ...$this->couleurs,
        ]);

        unset($this->themes, $this->themeCourant, $this->calendrier);

        $this->dispatch('toast', 'Thème enregistré', 'success');
    }

    public function nouveauTheme(): void
    {
        $theme = EmailTheme::query()->create([
            'code' => 'theme_'.Str::lower(Str::random(6)),
            'name' => 'Nouveau thème',
            // UN THEME NEUF NE PART PAS TOUT SEUL : il s'active apres un coup d'oeil a l'apercu.
            'is_active' => false,
            'is_default' => false,
            'priority' => 10,
            'footer_text' => 'Brio — plateforme de gestion des interventions.',
        ]);

        unset($this->themes, $this->calendrier);

        $this->ouvrir((int) $theme->id);
        $this->dispatch('toast', 'Thème créé — inactif tant que vous ne l’activez pas.', 'success');
    }

    public function dupliquer(): void
    {
        $source = $this->themeCourant;

        if (! $source instanceof EmailTheme) {
            return;
        }

        $copie = $source->replicate(['code']);
        $copie->code = $source->code.'_copie_'.Str::lower(Str::random(4));
        $copie->name = $source->name.' (copie)';
        $copie->is_default = false;
        $copie->is_active = false;
        $copie->save();

        unset($this->themes, $this->calendrier);

        $this->ouvrir((int) $copie->id);
        $this->dispatch('toast', 'Thème dupliqué, inactif.', 'success');
    }

    public function demanderLaSuppression(int $id): void
    {
        $this->themeASupprimer = $id;
    }

    public function annulerLaSuppression(): void
    {
        $this->themeASupprimer = null;
    }

    /**
     * LE THEME PAR DEFAUT NE SE SUPPRIME PAS.
     *
     * Il est le socle : l'effacer laisserait les gabarits qui l'imposent sans habillage, et le
     * repli du moteur ne serait plus qu'un pansement sur une decision perdue.
     */
    public function supprimer(): void
    {
        $theme = EmailTheme::query()->find($this->themeASupprimer);
        $this->themeASupprimer = null;

        if (! $theme instanceof EmailTheme) {
            return;
        }

        if ($theme->is_default) {
            $this->dispatch('toast', 'Le thème par défaut ne peut pas être supprimé.', 'warning');

            return;
        }

        $theme->delete();

        unset($this->themes, $this->themeCourant, $this->calendrier);

        $premier = $this->themes->first();
        $this->themeOuvert = null;

        if ($premier instanceof EmailTheme) {
            $this->ouvrir((int) $premier->id);
        }

        $this->dispatch('toast', 'Thème supprimé', 'success');
    }

    // ── Apercu ─────────────────────────────────────────────────────────────

    /** L'aperçu montre un vrai gabarit sous le thème EN COURS D'ÉDITION, pas sous l'enregistré. */
    #[Computed]
    public function apercu(): string
    {
        $gabarit = EmailTemplate::query()->where('code', $this->gabaritDApercu)->first()
            ?? EmailTemplate::query()->first();

        if (! $gabarit instanceof EmailTemplate) {
            return '';
        }

        $enCours = new EmailTheme([
            'logo_url' => $this->logo ?: null,
            'header_image_url' => $this->imageEntete ?: null,
            'background_image_url' => $this->imageFond ?: null,
            'footer_text' => $this->pied ?: null,
            'font_stack' => $this->police,
            'corner_radius' => $this->rayon,
            ...$this->couleurs,
        ]);

        return app(RenduDeBlocsEmail::class)->documentComplet(
            $gabarit->blocsPourLaLangue(),
            $enCours,
            $this->variablesDExemple(),
            (string) $gabarit->subject_pattern,
            $gabarit->preheader,
        );
    }

    /** @return array<string, scalar|null> */
    private function variablesDExemple(): array
    {
        return [
            'client_name' => 'Client Démo',
            'service' => 'Nettoyage standard',
            'date' => Carbon::now()->addDays(2)->format('d/m/Y'),
            'heure' => '09:00',
            'adresse' => 'Rue du Test 7, 1000 Bruxelles',
            'invoice_number' => 'B2B-2026-0042',
            'total' => '242,00 €',
            'balance' => '242,00 €',
            'due_date' => Carbon::now()->addDays(30)->format('d/m/Y'),
            'statut' => 'Confirmée',
            'priorite' => 'Haute',
            'action_url' => url('/'),
        ];
    }

    // ── Listes ─────────────────────────────────────────────────────────────

    /** @return Collection<int, EmailTheme> */
    #[Computed]
    public function themes(): Collection
    {
        return EmailTheme::query()->orderByDesc('is_default')->orderByDesc('priority')->orderBy('name')->get();
    }

    #[Computed]
    public function themeCourant(): ?EmailTheme
    {
        return $this->themeOuvert === null ? null : EmailTheme::query()->find($this->themeOuvert);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function calendrier(): array
    {
        return app(MoteurDeThemeEmail::class)->calendrier();
    }

    /** @return Collection<int, EmailTemplate> */
    #[Computed]
    public function gabarits(): Collection
    {
        return EmailTemplate::query()->orderBy('name')->get(['id', 'code', 'name']);
    }

    /** @return array<string, list<string>> */
    private function regles(): array
    {
        $regles = [
            'nom' => ['required', 'string', 'max:120'],
            'descriptionDuTheme' => ['nullable', 'string', 'max:300'],
            'debut' => ['nullable', 'date'],
            'fin' => ['nullable', 'date'],
            'priorite' => ['required', 'integer', 'min:0', 'max:999'],
            'logo' => ['nullable', 'url', 'max:500'],
            'imageEntete' => ['nullable', 'url', 'max:500'],
            'imageFond' => ['nullable', 'url', 'max:500'],
            'pied' => ['nullable', 'string', 'max:300'],
            'police' => ['required', 'string', 'max:200'],
            'rayon' => ['required', 'integer', 'min:0', 'max:40'],
        ];

        // UNE COULEUR EST UN CODE HEXADECIMAL, PAS UNE CHAINE LIBRE : elle part en style en ligne
        // dans un document envoye a l'exterieur.
        foreach (array_keys(self::COULEURS) as $champ) {
            $regles['couleurs.'.$champ] = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        return $regles;
    }

    public function render(): View
    {
        return view('livewire.admin.email-themes-studio');
    }
}
