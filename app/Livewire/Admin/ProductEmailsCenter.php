<?php

namespace App\Livewire\Admin;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\EmailTheme;
use App\Services\Email\EmailLogService;
use App\Services\Email\MoteurDeThemeEmail;
use App\Services\Email\RenduDeBlocsEmail;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * L'ATELIER D'E-MAILS : COMPOSER, HABILLER, VOIR.
 *
 * L'ecran ne savait qu'AFFICHER un apercu de six gabarits codes en dur. Il les EDITE desormais :
 * les gabarits vivent en base sous forme de blocs, l'administrateur les compose sans ecrire une
 * balise, et l'apercu se rejoue instantanement sous n'importe quel theme — celui du jour, celui
 * de Noel, celui de Black Friday — sans rien envoyer.
 *
 * L'APERCU RESTE DANS UN CADRE ISOLE. Un document d'e-mail injecte dans la page fusionne son
 * `<body>` avec celui de l'administration : un style en ligne se posait alors sur la vraie page
 * et repeignait le fond, mode sombre compris. `srcdoc` porte le document en attribut, echappe.
 *
 * @property-read Collection<int, EmailTemplate> $gabarits
 * @property-read Collection<int, EmailTheme> $themes
 * @property-read ?EmailTemplate $gabaritCourant
 * @property-read array<string, int> $reperes
 */
class ProductEmailsCenter extends Component
{
    use EnforcesAdminAccess;

    /** Le CODE du gabarit ouvert. Ce nom est un contrat : des tests le posent directement. */
    #[Url(as: 'gabarit', except: '')]
    public string $templateKey = '';

    #[Url(as: 'categorie', except: '')]
    public string $filtreCategorie = '';

    /** Deux volets d'un meme studio : ce qui est ECRIT, et ce qui l'HABILLE. */
    #[Url(as: 'volet', except: 'gabarits')]
    public string $onglet = 'gabarits';

    public string $recipientName = 'Client Démo';

    public string $recipientEmail = 'client@example.test';

    public string $previewHtml = '';

    public string $subject = '';

    // ── Le document en cours d'edition ─────────────────────────────────────
    public string $nom = '';

    public string $description = '';

    public string $categorie = 'transactionnel';

    public string $objet = '';

    public string $preheader = '';

    public string $variables = '';

    public string $themeImpose = '';

    public bool $actif = true;

    /** @var list<array<string, mixed>> */
    public array $blocs = [];

    /** Le theme SOUS LEQUEL on regarde, qui n'est pas celui qui partira forcement. */
    public string $themeDApercu = '';

    #[Locked]
    public ?int $gabaritASupprimer = null;

    public function boot(): void
    {
        Gate::authorize('manage-communication');
    }

    public function mount(): void
    {
        $premier = $this->gabarits->first();

        if ($this->templateKey === '' && $premier instanceof EmailTemplate) {
            $this->templateKey = (string) $premier->code;
        }

        $this->chargerLeGabarit();
        $this->generatePreview(false);
    }

    // ── Selection ──────────────────────────────────────────────────────────

    public function updatedTemplateKey(): void
    {
        $this->chargerLeGabarit();
        $this->generatePreview(false);
    }

    public function updatedFiltreCategorie(): void
    {
        unset($this->gabarits);
    }

    public function updatedThemeDApercu(): void
    {
        $this->generatePreview(false);
    }

    private function chargerLeGabarit(): void
    {
        $gabarit = $this->gabaritCourant;

        if (! $gabarit instanceof EmailTemplate) {
            $this->reinitialiserLeFormulaire();

            return;
        }

        $this->nom = (string) $gabarit->name;
        $this->description = (string) $gabarit->description;
        $this->categorie = (string) ($gabarit->category ?: 'transactionnel');
        $this->objet = (string) $gabarit->subject_pattern;
        $this->preheader = (string) $gabarit->preheader;
        $this->variables = implode(', ', (array) ($gabarit->required_variables ?? []));
        $this->themeImpose = (string) $gabarit->email_theme_id;
        $this->actif = (bool) $gabarit->is_active;
        $this->blocs = $gabarit->blocsPourLaLangue();
        $this->resetValidation();
    }

    private function reinitialiserLeFormulaire(): void
    {
        $this->reset(['nom', 'description', 'objet', 'preheader', 'variables', 'themeImpose', 'blocs']);
        $this->categorie = 'transactionnel';
        $this->actif = true;
    }

    // ── Le document ────────────────────────────────────────────────────────

    public function ajouterBloc(string $type): void
    {
        if (! in_array($type, RenduDeBlocsEmail::TYPES, true)) {
            return;
        }

        $this->blocs[] = match ($type) {
            'heading' => ['type' => 'heading', 'text' => 'Nouveau titre'],
            'paragraph' => ['type' => 'paragraph', 'text' => 'Écrivez votre message ici.'],
            'highlight' => ['type' => 'highlight', 'text' => 'Information à mettre en avant.'],
            'details' => ['type' => 'details', 'rows' => [['label' => 'Libellé', 'value' => 'Valeur']]],
            'button' => ['type' => 'button', 'text' => 'Ouvrir', 'url' => 'https://'],
            'image' => ['type' => 'image', 'url' => 'https://', 'alt' => ''],
            'divider' => ['type' => 'divider'],
            'spacer' => ['type' => 'spacer', 'height' => 16],
        };

        $this->generatePreview(false);
    }

    public function retirerBloc(int $index): void
    {
        unset($this->blocs[$index]);
        $this->blocs = array_values($this->blocs);
        $this->generatePreview(false);
    }

    public function deplacerBloc(int $index, int $sens): void
    {
        $cible = $index + $sens;

        if (! isset($this->blocs[$index], $this->blocs[$cible])) {
            return;
        }

        [$this->blocs[$index], $this->blocs[$cible]] = [$this->blocs[$cible], $this->blocs[$index]];
        $this->generatePreview(false);
    }

    public function ajouterLigne(int $index): void
    {
        if (($this->blocs[$index]['type'] ?? '') !== 'details') {
            return;
        }

        $this->blocs[$index]['rows'][] = ['label' => '', 'value' => ''];
        $this->generatePreview(false);
    }

    public function retirerLigne(int $index, int $ligne): void
    {
        unset($this->blocs[$index]['rows'][$ligne]);
        $this->blocs[$index]['rows'] = array_values($this->blocs[$index]['rows']);
        $this->generatePreview(false);
    }

    // ── Enregistrement ─────────────────────────────────────────────────────

    public function enregistrer(): void
    {
        $this->validate([
            'nom' => ['required', 'string', 'max:150'],
            'objet' => ['required', 'string', 'max:200'],
            'categorie' => ['required', 'in:transactionnel,rappel,marketing,fraude,interne'],
            'preheader' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            'themeImpose' => ['nullable', 'integer', 'exists:email_themes,id'],
            'blocs' => ['array'],
        ]);

        $gabarit = $this->gabaritCourant;

        if (! $gabarit instanceof EmailTemplate) {
            return;
        }

        $gabarit->update([
            'name' => $this->nom,
            'description' => $this->description ?: null,
            'category' => $this->categorie,
            'subject_pattern' => $this->objet,
            'preheader' => $this->preheader ?: null,
            'required_variables' => $this->variablesDeclarees(),
            'email_theme_id' => $this->themeImpose ?: null,
            'is_active' => $this->actif,
            'blocks' => $this->blocs,
        ]);

        unset($this->gabarits, $this->gabaritCourant, $this->reperes);

        $this->generatePreview(false);
        $this->dispatch('toast', 'Gabarit enregistré', 'success');
    }

    public function nouveauGabarit(): void
    {
        $code = 'nouveau_'.Str::lower(Str::random(6));

        EmailTemplate::query()->create([
            'code' => $code,
            'name' => 'Nouveau gabarit',
            'category' => 'transactionnel',
            'subject_pattern' => 'Objet à définir',
            'body_html_pattern' => '',
            'blocks' => [
                ['type' => 'heading', 'text' => 'Nouveau titre'],
                ['type' => 'paragraph', 'text' => 'Écrivez votre message ici.'],
            ],
            'required_variables' => [],
            'is_active' => false,
        ]);

        unset($this->gabarits, $this->reperes);

        $this->templateKey = $code;
        $this->chargerLeGabarit();
        $this->generatePreview(false);
        $this->dispatch('toast', 'Gabarit créé — il reste inactif tant que vous ne l’activez pas.', 'success');
    }

    public function dupliquer(): void
    {
        $source = $this->gabaritCourant;

        if (! $source instanceof EmailTemplate) {
            return;
        }

        $copie = $source->replicate(['code']);
        $copie->code = $source->code.'_copie_'.Str::lower(Str::random(4));
        $copie->name = $source->name.' (copie)';
        // UNE COPIE NE PART PAS TOUTE SEULE : elle s'active a la main, apres relecture.
        $copie->is_active = false;
        $copie->save();

        unset($this->gabarits, $this->reperes);

        $this->templateKey = (string) $copie->code;
        $this->chargerLeGabarit();
        $this->generatePreview(false);
        $this->dispatch('toast', 'Gabarit dupliqué, inactif.', 'success');
    }

    public function demanderLaSuppression(int $id): void
    {
        $this->gabaritASupprimer = $id;
    }

    public function annulerLaSuppression(): void
    {
        $this->gabaritASupprimer = null;
    }

    public function supprimer(): void
    {
        if ($this->gabaritASupprimer === null) {
            return;
        }

        EmailTemplate::query()->whereKey($this->gabaritASupprimer)->delete();

        $this->gabaritASupprimer = null;
        unset($this->gabarits, $this->gabaritCourant, $this->reperes);

        $this->templateKey = (string) ($this->gabarits->first()->code ?? '');
        $this->chargerLeGabarit();
        $this->generatePreview(false);
        $this->dispatch('toast', 'Gabarit supprimé', 'success');
    }

    // ── Apercu ─────────────────────────────────────────────────────────────

    /**
     * L'APERCU, SOUS LE THEME DEMANDE.
     *
     * `$log` reste faux tant que l'administrateur tape : journaliser chaque frappe noierait la
     * trace utile — celle du geste volontaire — dans des centaines de lignes.
     */
    public function generatePreview(bool $log = true): void
    {
        $gabarit = $this->gabaritCourant;

        if (! $gabarit instanceof EmailTemplate) {
            $this->previewHtml = '';
            $this->subject = '';

            return;
        }

        $theme = $this->themeDApercu !== ''
            ? EmailTheme::query()->find($this->themeDApercu) ?? app(MoteurDeThemeEmail::class)->pour($gabarit)
            : app(MoteurDeThemeEmail::class)->pour($gabarit);

        $variables = $this->variablesDExemple();

        $this->subject = strtr($this->objet ?: (string) $gabarit->subject_pattern,
            collect($variables)->mapWithKeys(fn ($v, $k) => ['{{'.$k.'}}' => (string) $v])->all());

        $this->previewHtml = app(RenduDeBlocsEmail::class)->documentComplet(
            $this->blocs,
            $theme,
            $variables,
            $this->subject,
            $this->preheader ?: null,
        );

        if ($log) {
            app(EmailLogService::class)->logPreview(
                $this->templateKey,
                $this->subject,
                $this->recipientEmail,
                auth()->id(),
                ['recipient_name' => $this->recipientName],
            );

            $this->dispatch('toast', 'Aperçu email généré.', 'success');
        }
    }

    /**
     * DES VALEURS D'EXEMPLE POUR CHAQUE VARIABLE DECLAREE.
     *
     * Un apercu qui laisse voir `{{client_name}}` ne montre pas l'e-mail, il montre son gabarit.
     *
     * @return array<string, scalar|null>
     */
    private function variablesDExemple(): array
    {
        $connues = [
            'client_name' => $this->recipientName,
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

        $exemples = [];

        foreach ($this->variablesDeclarees() as $variable) {
            $exemples[$variable] = $connues[$variable] ?? Str::headline($variable);
        }

        return $exemples + $connues;
    }

    /** @return list<string> */
    private function variablesDeclarees(): array
    {
        return collect(explode(',', $this->variables))
            ->map(fn (string $v) => preg_replace('/[^a-zA-Z0-9_]/', '', trim($v)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    // ── Listes ─────────────────────────────────────────────────────────────

    /** @return Collection<int, EmailTemplate> */
    #[Computed]
    public function gabarits(): Collection
    {
        return EmailTemplate::query()
            ->when($this->filtreCategorie !== '', fn ($q) => $q->where('category', $this->filtreCategorie))
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function gabaritCourant(): ?EmailTemplate
    {
        return $this->templateKey === ''
            ? null
            : EmailTemplate::query()->where('code', $this->templateKey)->first();
    }

    /** @return Collection<int, EmailTheme> */
    #[Computed]
    public function themes(): Collection
    {
        return EmailTheme::query()->orderByDesc('is_default')->orderBy('name')->get();
    }

    /** @return array<string, int> */
    #[Computed]
    public function reperes(): array
    {
        return [
            'gabarits' => EmailTemplate::query()->count(),
            'actifs' => EmailTemplate::query()->where('is_active', true)->count(),
            'themes' => EmailTheme::query()->where('is_active', true)->count(),
            'saisons' => EmailTheme::query()->where('is_default', false)->whereNotNull('starts_on')->count(),
        ];
    }

    /** @return Collection<int, EmailLog> */
    public function getRecentLogsProperty(): Collection
    {
        if (! app(EmailLogService::class)->available()) {
            return collect();
        }

        return EmailLog::latest()->limit(8)->get();
    }

    public function render()
    {
        return view('livewire.admin.product-emails-center', [
            'recentLogs' => $this->recentLogs,
        ]);
    }
}
