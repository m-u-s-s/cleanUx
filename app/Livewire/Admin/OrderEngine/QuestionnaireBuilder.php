<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Livewire\OrderEngine\QuestionRenderer;
use App\Models\Contracts\TranslatesCatalogLabels;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Services\OrderEngine\CatalogArchiver;
use App\Services\OrderEngine\PriceBreakdown;
use App\Services\OrderEngine\PricingEngine;
use App\Services\OrderEngine\QuestionnairePortability;
use App\Services\OrderEngine\QuestionnaireValidator;
use App\Services\OrderEngine\TradeFormPublisher;
use App\Support\Domain\OrderMode;
use App\Support\Domain\PriceImpactMode;
use App\Support\Domain\QuestionType;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Le constructeur de parcours : édition à gauche, aperçu à droite.
 *
 * L'aperçu monte le VRAI {@see QuestionRenderer} — celui que le client
 * utilisera. C'est ce qui rend la promesse tenable : un aperçu qui réimplémenterait le rendu
 * finirait par diverger, et personne ne s'en apercevrait avant la mise en ligne.
 *
 * Le simulateur de prix n'est pas un gadget. Une grille tarifaire faite d'additions, de
 * multiplicateurs et de coefficients par unité ne se vérifie pas de tête : répondre au
 * questionnaire et voir le prix se construire ligne par ligne est le seul moyen fiable de la
 * valider avant de la mettre en ligne.
 */
#[Layout('layouts.app')]
class QuestionnaireBuilder extends Component
{
    /**
     * Défense en profondeur : le refus vaut au niveau du COMPOSANT, pas seulement de la route.
     *
     * `/admin/*` est déjà derrière `role:admin`, mais un composant Livewire se monte aussi hors de
     * ce chemin — et cet écran-ci écrit le catalogue, verrouille des codes et archive des
     * questions. La garantie doit survivre à un remaniement de routes.
     */
    use EnforcesAdminAccess;

    public Trade $trade;

    /** Question en cours d'édition. `0` = création. */
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** Réponses saisies dans l'aperçu — elles alimentent le simulateur, rien d'autre. */
    public array $answers = [];

    /** Mode simulé : la majoration d'urgence doit se voir avant d'être mise en ligne. */
    public string $previewMode = OrderMode::SCHEDULED;

    /** Question dont l'archivage est en cours de confirmation. */
    public ?int $archivingId = null;

    /** @var array<string, mixed>|null */
    public ?array $archiveImpact = null;

    public ?string $flash = null;

    public function mount(Trade $trade): void
    {
        $this->trade = $trade;
        $this->resetForm();
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────────────────────

    /** @return Collection<int, Question> */
    #[Computed]
    public function questions()
    {
        return $this->trade->questions()
            ->with(['options.translations', 'conditions', 'translations'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Les avertissements du validateur, tels que l'administrateur doit les voir.
     *
     * Distingués par gravité : ce qui coûte des clients est signalé, ce qui casse le parcours
     * empêche la publication.
     */
    #[Computed]
    public function issues(): array
    {
        return app(QuestionnaireValidator::class)->inspect($this->trade);
    }

    #[Computed]
    public function canPublish(): bool
    {
        return app(QuestionnaireValidator::class)->canPublish($this->trade);
    }

    /** Le prix construit par les réponses de l'aperçu, explicable ligne par ligne. */
    #[Computed]
    public function quote(): PriceBreakdown
    {
        return app(PricingEngine::class)->quoteItem(
            $this->trade,
            $this->questions(),
            $this->answers,
            ['mode' => $this->previewMode],
        );
    }

    /**
     * Le simulateur écoute le vrai composant client.
     *
     * L'administrateur répond donc exactement comme un client, et voit le même prix — pas une
     * approximation calculée à part.
     */
    #[On('question-answered')]
    public function recordAnswer(string $code, mixed $value, bool $valid): void
    {
        $this->answers[$code] = $value;
        unset($this->quote);
    }

    // ─── Édition ─────────────────────────────────────────────────────────────────────────────

    public function startNew(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $questionId): void
    {
        $question = $this->questions()->firstWhere('id', $questionId);
        if (! $question) {
            return;
        }

        $this->editingId = $question->id;
        $this->form = [
            'label' => $question->label,
            'code' => $question->code,
            'help_text' => $question->help_text,
            'type' => $question->type,
            'layout' => $question->display['layout'] ?? 'cards',
            'is_required' => (bool) $question->is_required,
            'allows_unknown' => (bool) $question->allows_unknown,
            'is_essential' => (bool) $question->is_essential,
            'unit' => $question->validation['unit'] ?? null,
            'min' => $question->validation['min'] ?? null,
            'max' => $question->validation['max'] ?? null,
            'step' => $question->validation['step'] ?? null,
            'pricing_mode' => $question->pricing['mode'] ?? PriceImpactMode::NONE,
            'pricing_coefficient' => $question->pricing['coefficient'] ?? null,
        ];
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    /**
     * Le CODE ne se modifie plus dès qu'une réponse existe.
     *
     * C'est la clé sous laquelle les réponses sont enregistrées : le renommer rendrait
     * inexplicables tous les devis qui le citent — ils pointeraient vers une clé qui n'existe
     * plus. L'interface le verrouille donc, plutôt que de compter sur la prudence de qui l'emploie.
     */
    public function codeIsLocked(): bool
    {
        if (! $this->editingId) {
            return false;
        }

        $question = $this->questions()->firstWhere('id', $this->editingId);

        return $question !== null && app(CatalogArchiver::class)->impactOf($question)['used_count'] > 0;
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.label' => ['required', 'string', 'max:190'],
            'form.code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'form.type' => ['required', 'string'],
            'form.help_text' => ['nullable', 'string', 'max:190'],
            'form.min' => ['nullable', 'numeric'],
            'form.max' => ['nullable', 'numeric'],
            'form.step' => ['nullable', 'numeric', 'min:0'],
            'form.pricing_coefficient' => ['nullable', 'numeric'],
        ], [
            'form.code.regex' => 'Le code ne peut contenir que des minuscules, des chiffres et des tirets bas.',
        ])['form'];

        $payload = [
            'label' => $data['label'],
            'help_text' => $data['help_text'] ?? null,
            'type' => $data['type'],
            'is_required' => (bool) ($this->form['is_required'] ?? false),
            'allows_unknown' => (bool) ($this->form['allows_unknown'] ?? true),
            'is_essential' => (bool) ($this->form['is_essential'] ?? false),
            'display' => ['layout' => $this->form['layout'] ?? 'cards'],
            'validation' => array_filter([
                'unit' => $this->form['unit'] ?? null,
                'min' => $data['min'] ?? null,
                'max' => $data['max'] ?? null,
                'step' => $data['step'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'pricing' => [
                'mode' => $this->form['pricing_mode'] ?? PriceImpactMode::NONE,
                'coefficient' => $data['pricing_coefficient'] ?? 0,
            ],
        ];

        if ($this->editingId) {
            $question = $this->questions()->firstWhere('id', $this->editingId);

            // Le code n'entre dans la mise à jour que s'il est encore modifiable.
            if (! $this->codeIsLocked()) {
                $payload['code'] = $data['code'];
            }

            $question?->update($payload);
            $this->flash = 'Question mise à jour.';
        } else {
            Question::create($payload + [
                'trade_id' => $this->trade->id,
                'code' => $data['code'],
                'sort_order' => (int) $this->questions()->max('sort_order') + 1,
                'is_active' => true,
            ]);
            $this->flash = 'Question ajoutée.';
        }

        $this->cancel();
        $this->refreshDerived();
    }

    /** Le libellé propose un code, tant que celui-ci n'a pas été touché — on ne le devine jamais deux fois. */
    public function updatedFormLabel(string $value): void
    {
        if (! $this->editingId && blank($this->form['code'] ?? null)) {
            $this->form['code'] = Str::of($value)->slug('_')->limit(60, '')->toString();
        }
    }

    // ─── Ordre, activation, archivage ────────────────────────────────────────────────────────

    /**
     * Réordonnancement enregistré IMMÉDIATEMENT.
     *
     * Un ordre qu'il faut penser à enregistrer finit par être perdu : l'administrateur réorganise,
     * change d'écran, et retrouve son ancien parcours sans comprendre.
     */
    public function move(int $questionId, int $direction): void
    {
        $ordered = $this->questions()->values();
        $index = $ordered->search(fn ($q) => $q->id === $questionId);

        if ($index === false) {
            return;
        }

        $target = $index + $direction;
        if ($target < 0 || $target >= $ordered->count()) {
            return;
        }

        DB::transaction(function () use ($ordered, $index, $target) {
            $ordered->each(fn ($q, $i) => $q->update(['sort_order' => $i]));
            $ordered[$index]->update(['sort_order' => $target]);
            $ordered[$target]->update(['sort_order' => $index]);
        });

        $this->refreshDerived();
    }

    /**
     * Réordonne d'un geste : la liste complète arrive du navigateur.
     *
     * Le serveur ne fait PAS confiance à l'ordre reçu — il ne retient que les identifiants qui
     * appartiennent réellement à ce métier. Sans ce tri, un identifiant glissé dans la requête
     * réordonnerait le questionnaire d'un autre métier.
     *
     * @param  list<int|string>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        $own = $this->questions()->keyBy('id');

        $kept = collect($orderedIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $own->has($id))
            ->values();

        // Une liste partielle laisserait des questions sans rang défini, donc à une place
        // arbitraire au prochain affichage : on refuse plutôt que de réordonner à moitié.
        if ($kept->count() !== $own->count()) {
            return;
        }

        DB::transaction(function () use ($kept, $own) {
            $kept->each(fn (int $id, int $position) => $own[$id]->update(['sort_order' => $position]));
        });

        $this->refreshDerived();
    }

    /**
     * Reprend une question de la bibliothèque dans ce métier.
     *
     * Une COPIE, pas un partage. Les questions restent au niveau du métier : un libellé partagé en
     * direct entre douze métiers ferait qu'un ajustement de prix pour la peinture déplacerait aussi
     * celui de la plomberie.
     */
    public function adoptFromLibrary(int $questionId): void
    {
        $template = Question::query()->library()->find($questionId);

        if (! $template) {
            return;
        }

        // Le code est la clé stable du métier : s'il est déjà pris, on ne l'écrase pas — ce serait
        // remplacer une question déjà répondue par des clients en cours de commande.
        if ($this->trade->questions()->where('code', $template->code)->exists()) {
            $this->flash = sprintf('Le code « %s » existe déjà dans ce métier.', $template->code);

            return;
        }

        DB::transaction(function () use ($template) {
            $copy = $template->replicate(['trade_id', 'step_id', 'sort_order']);
            $copy->trade_id = $this->trade->id;
            $copy->sort_order = (int) $this->trade->questions()->max('sort_order') + 1;
            $copy->save();

            foreach ($template->options as $option) {
                $optionCopy = $option->replicate(['question_id']);
                $optionCopy->question_id = $copy->id;
                $optionCopy->save();

                // Les traductions suivent la copie : sans elles, reprendre une question de la
                // bibliothèque ferait perdre le néerlandais et l'anglais déjà écrits.
                $this->copyTranslations($option, $optionCopy);
            }

            $this->copyTranslations($template, $copy);
        });

        $this->flash = sprintf('« %s » reprise depuis la bibliothèque.', $template->label);
        $this->refreshDerived();
    }

    /**
     * Écrit — ou retire — un libellé traduit.
     *
     * Vider le champ SUPPRIME la traduction : revenir au libellé de base doit être aussi simple
     * que d'effacer, et ne doit surtout pas produire une question blanche en production.
     */
    public function saveTranslation(int $questionId, string $locale, string $field, ?string $value): void
    {
        if (! array_key_exists($locale, $this->translationLocales())) {
            return;
        }

        // La liste des champs traduisibles est FERMÉE : sans elle, un champ arbitraire venu du
        // navigateur créerait des lignes que rien ne lit jamais.
        if (! in_array($field, ['label', 'help_text', 'placeholder'], true)) {
            return;
        }

        $question = $this->questions()->firstWhere('id', $questionId);
        $question?->setTranslation($field, $locale, $value);

        $this->refreshDerived();
    }

    /**
     * Les langues à traduire — celles activées, moins celle des libellés de base.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function translationLocales(): array
    {
        $default = (string) config('i18n.default', 'fr');

        return collect(config('i18n.locales', []))
            ->filter(fn ($meta) => (bool) ($meta['enabled'] ?? false))
            ->reject(fn ($meta, $code) => $code === $default)
            ->map(fn ($meta) => (string) ($meta['native_name'] ?? $meta['name'] ?? ''))
            ->all();
    }

    /** Les questions réutilisables que ce métier n'a pas encore reprises. */
    #[Computed]
    public function libraryQuestions()
    {
        $taken = $this->trade->questions()->pluck('code');

        return Question::query()
            ->library()
            ->where('is_active', true)
            ->whereNotIn('code', $taken)
            ->orderBy('label')
            ->get();
    }

    /**
     * Recopie les libellés traduits d'un objet du catalogue vers sa copie.
     *
     * Le type dit ce qui est réellement exigé : un modèle PORTANT le trait de traduction. Accepter
     * un `Model` quelconque laissait passer, à la compilation comme à l'analyse, un appel qui
     * planterait à l'exécution sur un modèle sans `setTranslation()`.
     */
    protected function copyTranslations(TranslatesCatalogLabels $from, TranslatesCatalogLabels $to): void
    {
        foreach ($from->translations()->get() as $translation) {
            $to->setTranslation($translation->field, $translation->locale, $translation->value);
        }
    }

    /** Désactiver n'est pas archiver : la question quitte le parcours, elle reste sous la main. */
    public function toggleActive(int $questionId): void
    {
        $question = $this->questions()->firstWhere('id', $questionId);
        $question?->update(['is_active' => ! $question->is_active]);

        $this->refreshDerived();
    }

    /** L'impact est annoncé AVANT. Un administrateur qui le découvre après n'a plus de recours. */
    public function confirmArchive(int $questionId): void
    {
        $question = $this->questions()->firstWhere('id', $questionId);
        if (! $question) {
            return;
        }

        $this->archivingId = $questionId;
        $this->archiveImpact = app(CatalogArchiver::class)->impactOf($question);
    }

    public function archive(): void
    {
        $question = $this->questions()->firstWhere('id', $this->archivingId);

        if ($question) {
            app(CatalogArchiver::class)->archive($question);
            $this->flash = 'Question archivée. Les devis qui la citent restent lisibles.';
        }

        $this->archivingId = null;
        $this->archiveImpact = null;
        $this->refreshDerived();
    }

    public function cancelArchive(): void
    {
        $this->archivingId = null;
        $this->archiveImpact = null;
    }

    // ─── Publication et portabilité ──────────────────────────────────────────────────────────

    /** La version en ligne, celle que les commandes citent. */
    #[Computed]
    public function currentRevision()
    {
        return app(TradeFormPublisher::class)->currentRevision($this->trade);
    }

    /**
     * Le brouillon diffère-t-il de ce qui est en ligne ?
     *
     * Comparaison sur le CONTENU : renommer une question puis annuler laisse une trace dans
     * `updated_at` sans rien changer au parcours, et signaler « en attente » dans ce cas
     * apprendrait à l'administrateur à ignorer l'avertissement.
     */
    #[Computed]
    public function hasUnpublishedChanges(): bool
    {
        return app(TradeFormPublisher::class)->hasUnpublishedChanges($this->trade);
    }

    /** Met en ligne, en figeant une version. Refusé si un défaut bloquant s'y oppose. */
    public function publish(): void
    {
        try {
            $revision = app(TradeFormPublisher::class)->publish($this->trade, Auth::user());
            $this->flash = sprintf('Parcours publié — version %d. Les commandes en cours citeront celle-ci.', $revision->version);
        } catch (ValidationException $e) {
            $this->flash = null;
            $this->addError('publication', collect($e->errors())->flatten()->implode(' '));
        }

        $this->refreshDerived();
    }

    /**
     * Recopie ce questionnaire vers un autre métier.
     *
     * « Peinture intérieure » et « Peinture extérieure » partagent l'essentiel de leurs questions ;
     * les ressaisir produit deux formulations légèrement différentes de la même chose, et un client
     * qui ne comprend pas pourquoi le même mur coûte deux prix.
     */
    public function duplicateTo(int $targetTradeId): void
    {
        $target = Trade::find($targetTradeId);

        if (! $target || $target->id === $this->trade->id) {
            return;
        }

        $result = app(QuestionnairePortability::class)->duplicate($this->trade, $target);

        $this->flash = sprintf(
            'Copié vers « %s » : %d question(s) ajoutée(s), %d mise(s) à jour.',
            $target->name,
            $result['created'],
            $result['updated'],
        );
    }

    /** Export JSON — pour rejouer un questionnaire d'un environnement à l'autre. */
    public function export()
    {
        $payload = app(QuestionnairePortability::class)->export($this->trade);

        return response()->streamDownload(
            fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            sprintf('parcours-%s.json', $this->trade->slug),
            ['Content-Type' => 'application/json'],
        );
    }

    /** Les métiers vers lesquels dupliquer. */
    #[Computed]
    public function duplicationTargets()
    {
        return Trade::query()
            ->where('id', '!=', $this->trade->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    // ─── Options ─────────────────────────────────────────────────────────────────────────────

    public function addOption(int $questionId): void
    {
        $question = $this->questions()->firstWhere('id', $questionId);
        if (! $question) {
            return;
        }

        QuestionOption::create([
            'question_id' => $question->id,
            'label' => 'Nouvelle réponse',
            'value' => 'option_'.($question->options->count() + 1),
            'sort_order' => $question->options->count(),
            // Jamais par défaut d'office : deux défauts sur une question feraient dépendre
            // l'écran de l'ordre de tri, et le validateur refuserait la publication.
            'is_default' => $question->options->isEmpty(),
        ]);

        $this->refreshDerived();
    }

    /** @param  array<string, mixed>  $values */
    public function updateOption(int $optionId, array $values): void
    {
        $option = QuestionOption::find($optionId);
        if (! $option) {
            return;
        }

        // Un seul défaut par question : poser celui-ci retire l'autre, plutôt que de laisser le
        // validateur refuser la publication pour une raison que l'administrateur ne verrait pas.
        if (! empty($values['is_default'])) {
            QuestionOption::where('question_id', $option->question_id)->update(['is_default' => false]);
        }

        $option->update($values);
        $this->refreshDerived();
    }

    public function archiveOption(int $optionId): void
    {
        $option = QuestionOption::find($optionId);

        if ($option) {
            app(CatalogArchiver::class)->archive($option);
        }

        $this->refreshDerived();
    }

    // ─── Interne ─────────────────────────────────────────────────────────────────────────────

    protected function resetForm(): void
    {
        $this->form = [
            'label' => '',
            'code' => '',
            'help_text' => null,
            'type' => QuestionType::SINGLE_CHOICE,
            'layout' => 'cards',
            'is_required' => false,
            // La porte de sortie est ouverte par défaut : il faut une raison explicite de poser un mur.
            'allows_unknown' => true,
            'is_essential' => false,
            'unit' => null,
            'min' => null,
            'max' => null,
            'step' => null,
            'pricing_mode' => PriceImpactMode::NONE,
            'pricing_coefficient' => null,
        ];
    }

    /** Les propriétés calculées sont mises en cache par Livewire : il faut les invalider à la main. */
    protected function refreshDerived(): void
    {
        unset($this->questions, $this->issues, $this->canPublish, $this->quote, $this->currentRevision, $this->hasUnpublishedChanges);
    }

    public function questionTypes(): array
    {
        return QuestionType::all();
    }

    public function render()
    {
        return view('livewire.admin.order-engine.questionnaire-builder');
    }
}
