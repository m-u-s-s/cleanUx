<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Livewire\OrderEngine\QuestionRenderer;
use App\Models\Contracts\TranslatesCatalogLabels;
use App\Models\Question;
use App\Models\QuestionCondition;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Services\OrderEngine\CatalogArchiver;
use App\Services\OrderEngine\PriceBreakdown;
use App\Services\OrderEngine\PricingEngine;
use App\Services\OrderEngine\QuestionInsights;
use App\Services\OrderEngine\QuestionnairePortability;
use App\Services\OrderEngine\QuestionnaireValidator;
use App\Services\OrderEngine\TradeFormPublisher;
use App\Support\Domain\ConditionAction;
use App\Support\Domain\ConditionOperator;
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
use Livewire\WithFileUploads;

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

    use WithFileUploads;

    /**
     * En deçà, aucun verdict n'est rendu : le service se tait, et l'écran DIT qu'il se tait.
     * La même valeur que le seuil de `QuestionInsights::worstOffenders()`.
     */
    private const MINIMUM_ORDERS_TO_CONCLUDE = 20;

    public Trade $trade;

    /** Fichier JSON d'un parcours exporté, en attente d'import. */
    public $importFile = null;

    /**
     * Condition en cours d'écriture : « Afficher X SI Y EST Z ».
     *
     * `question_id` à `null` = aucun éditeur ouvert. Le moteur de conditions existait complet et
     * testé sans aucune interface : un administrateur ne pouvait ni créer, ni supprimer une seule
     * règle, ce qui rendait faux « ajouter un métier et ses questions sans une ligne de code » dès
     * le premier exemple de la spécification.
     *
     * @var array<string, mixed>
     */
    public array $conditionForm = [
        'question_id' => null,
        'depends_on_question_id' => null,
        'operator' => ConditionOperator::EQUALS,
        'value' => '',
        'action' => ConditionAction::SHOW,
    ];

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

    /**
     * Le lecteur seul lit.
     *
     * `EnforcesAdminAccess` s'arrête à « est-ce un administrateur » et le dit lui-même : les
     * restrictions d'écriture du lecteur seul restent à la charge du composant. Un `platform_role`
     * à « admin » assorti d'un `access_scope` à « readonly » franchit donc la garde et atteint cet
     * écran — qui écrit le catalogue, verrouille des codes et fige des contrats de prix.
     *
     * Le refus est ANNONCÉ plutôt que silencieux : un bouton qui ne fait rien fait recommencer,
     * puis appeler le support.
     */
    private function refusesWrite(string $errorKey = 'publication'): bool
    {
        $user = Auth::user();

        if ($user === null || ! method_exists($user, 'isReadOnlyAdmin') || ! $user->isReadOnlyAdmin()) {
            return false;
        }

        $this->flash = null;
        $this->addError(
            $errorKey,
            'Votre accès est en lecture seule. Publier un parcours et modifier le catalogue sont réservés aux administrateurs de plein exercice.',
        );

        return true;
    }

    public function save(): void
    {
        if ($this->refusesWrite('form.label')) {
            return;
        }

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
        if ($this->refusesWrite()) {
            return;
        }

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
        if ($this->refusesWrite()) {
            return;
        }

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
        if ($this->refusesWrite()) {
            return;
        }

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
        if ($this->refusesWrite()) {
            return;
        }

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
        if ($this->refusesWrite()) {
            return;
        }

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
        if ($this->refusesWrite()) {
            return;
        }

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
        if ($this->refusesWrite()) {
            return;
        }

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
        if ($this->refusesWrite()) {
            return;
        }

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

    // ─── Ce que les clients font du parcours ─────────────────────────────────────────────────

    /**
     * L'abandon par question, indexé par code.
     *
     * Ces chiffres n'ont de valeur QUE sous les yeux de qui ajoute la question suivante. Un
     * parcours ne devient pas trop long d'un coup : il s'allonge d'une question à la fois, chacune
     * justifiable prise isolément, et la conversion s'érode sans que personne ne sache où.
     *
     * Calculé UNE fois pour tout l'écran : le service parcourt déjà l'ensemble des lignes de
     * commande du métier, l'appeler par question multiplierait ce parcours par dix.
     *
     * @return Collection<string, array<string, mixed>>
     */
    #[Computed]
    public function insights(): Collection
    {
        return app(QuestionInsights::class)->forTrade($this->trade)->keyBy('code');
    }

    /**
     * Les codes des questions qui font réellement décrocher.
     *
     * Le service tient les deux garde-fous : un seuil de taux ET un volume minimum. Un abandon sur
     * trois commandes ne dit rien, et l'afficher comme « 33 % » ferait supprimer une question
     * parfaitement saine.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function losingQuestionCodes(): array
    {
        return app(QuestionInsights::class)
            ->worstOffenders($this->trade)
            ->pluck('code')
            ->all();
    }

    /**
     * Y a-t-il assez de commandes pour se prononcer ?
     *
     * Un écran qui se contente de ne rien afficher laisse croire que tout va bien. Il doit
     * distinguer « aucun problème » de « pas encore de quoi conclure » — sinon l'absence de
     * signal se lit comme un satisfecit.
     */
    #[Computed]
    public function hasEnoughOrdersToConclude(): bool
    {
        return ($this->insights->first()['reached'] ?? 0) >= self::MINIMUM_ORDERS_TO_CONCLUDE;
    }

    // ─── Conditions ──────────────────────────────────────────────────────────────────────────

    /** Ouvre l'éditeur pour la question qui SUBIT la condition. */
    public function startCondition(int $questionId): void
    {
        $this->conditionForm = [
            'question_id' => $questionId,
            'depends_on_question_id' => null,
            'operator' => ConditionOperator::EQUALS,
            'value' => '',
            'action' => ConditionAction::SHOW,
        ];
    }

    public function cancelCondition(): void
    {
        $this->conditionForm['question_id'] = null;
    }

    /**
     * Enregistre « Afficher X SI Y EST Z ».
     *
     * DEUX REFUS À LA SAISIE, et non à la publication.
     *
     * Le validateur bloquait déjà les cycles au moment de mettre en ligne. Mais un administrateur
     * qui a écrit trois règles et découvre à la publication que l'une d'elles est fautive doit
     * refaire le chemin à l'envers pour trouver laquelle. Refuser au moment du geste dit QUELLE
     * règle pose problème, pendant qu'il l'a encore sous les yeux.
     */
    public function saveCondition(): void
    {
        if ($this->refusesWrite('conditionForm.depends_on_question_id')) {
            return;
        }

        $data = $this->validate([
            'conditionForm.question_id' => ['required', 'integer'],
            'conditionForm.depends_on_question_id' => ['required', 'integer'],
            'conditionForm.operator' => ['required', 'string'],
            'conditionForm.action' => ['required', 'string'],
        ], [
            'conditionForm.depends_on_question_id.required' => 'Choisissez la question dont dépend l’affichage.',
        ])['conditionForm'];

        $subject = $this->questions()->firstWhere('id', (int) $data['question_id']);
        $dependsOn = $this->questions()->firstWhere('id', (int) $data['depends_on_question_id']);

        if (! $subject || ! $dependsOn) {
            return;
        }

        if ($subject->id === $dependsOn->id) {
            $this->addError(
                'conditionForm.depends_on_question_id',
                'Une question ne peut pas dépendre d’elle-même : elle ne s’afficherait jamais.',
            );

            return;
        }

        if ($this->wouldLoop($subject->id, $dependsOn->id)) {
            $this->addError(
                'conditionForm.depends_on_question_id',
                sprintf(
                    '« %s » dépend déjà de « %s » : les deux s’attendraient l’une l’autre et aucune ne s’afficherait. Choisissez une autre question.',
                    $dependsOn->label,
                    $subject->label,
                ),
            );

            return;
        }

        QuestionCondition::create([
            'question_id' => $subject->id,
            'depends_on_question_id' => $dependsOn->id,
            'operator' => $data['operator'],
            // La valeur est toujours rangée en LISTE : `in` en attend plusieurs, et les autres
            // opérateurs s'accommodent d'une liste à un élément. Une forme unique évite d'avoir à
            // deviner, à la lecture, si l'on tient une valeur ou un tableau.
            'value' => $this->conditionValues($data['operator'], (string) ($data['value'] ?? '')),
            'action' => $data['action'],
        ]);

        $this->conditionForm['question_id'] = null;
        unset($this->questions);
        $this->refreshDerived();
        $this->flash = 'Règle enregistrée.';
    }

    public function removeCondition(int $conditionId): void
    {
        if ($this->refusesWrite('conditionForm.depends_on_question_id')) {
            return;
        }

        $ids = $this->questions()->pluck('id');

        // Une condition d'un AUTRE métier ne se supprime pas depuis cet écran.
        QuestionCondition::query()
            ->whereIn('question_id', $ids)
            ->where('id', $conditionId)
            ->delete();

        unset($this->questions);
        $this->refreshDerived();
    }

    /**
     * @return array<int, string>
     */
    protected function conditionValues(string $operator, string $raw): array
    {
        if ($operator === ConditionOperator::IS_ANSWERED) {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn (string $v) => trim($v))
            ->filter(fn (string $v) => $v !== '')
            ->values()
            ->all();
    }

    /**
     * Ajouter cette dépendance créerait-il un cycle ?
     *
     * On remonte la chaîne depuis la question DONT on veut dépendre : si elle dépend déjà, de
     * proche en proche, de celle qu'on est en train de configurer, les deux s'attendraient l'une
     * l'autre et ni l'une ni l'autre ne s'afficherait jamais. Le défaut ne lève aucune erreur : il
     * supprime silencieusement une partie du parcours.
     */
    protected function wouldLoop(int $subjectId, int $dependsOnId): bool
    {
        $edges = QuestionCondition::query()
            ->whereIn('question_id', $this->questions()->pluck('id'))
            ->get()
            ->groupBy('question_id');

        $seen = [];
        $stack = [$dependsOnId];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $subjectId) {
                return true;
            }

            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            foreach ($edges[$current] ?? [] as $edge) {
                $stack[] = (int) $edge->depends_on_question_id;
            }
        }

        return false;
    }

    /**
     * Les opérateurs, dits comme on les lirait à voix haute.
     *
     * « equals » ne se lit pas ; « est égal à » se lit. C'est toute la différence entre un éditeur
     * qu'un responsable non technique emploie seul et un formulaire qu'il faut lui traduire.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function conditionOperators(): array
    {
        return [
            ConditionOperator::EQUALS => 'est égal à',
            ConditionOperator::NOT_EQUALS => 'est différent de',
            ConditionOperator::IN => 'fait partie de',
            ConditionOperator::GT => 'est supérieur à',
            ConditionOperator::LT => 'est inférieur à',
            ConditionOperator::IS_ANSWERED => 'a reçu une réponse',
        ];
    }

    /** @return array<string, string> */
    #[Computed]
    public function conditionActions(): array
    {
        return [
            ConditionAction::SHOW => 'Afficher',
            ConditionAction::HIDE => 'Masquer',
            ConditionAction::REQUIRE => 'Rendre obligatoire',
        ];
    }

    /**
     * Import JSON — l'autre moitié du voyage.
     *
     * Le service savait déjà écrire un questionnaire depuis un fichier ; rien ne l'appelait. Sortir
     * un parcours d'un environnement sans pouvoir le faire entrer dans l'autre ne sert à rien, et
     * la moitié manquante n'était atteignable qu'avec un tinker ouvert.
     *
     * L'import ne SUPPRIME rien : une question absente du fichier reste en place, et une question
     * archivée n'est pas ressuscitée. Rejouer deux fois le même fichier met à jour au lieu de
     * dupliquer — c'est ce qui permet de synchroniser deux environnements sans les empiler.
     */
    public function import(): void
    {
        if ($this->refusesWrite('importFile')) {
            return;
        }

        $this->validate(
            ['importFile' => ['required', 'file', 'max:2048']],
            ['importFile.required' => 'Choisissez le fichier JSON d’un parcours exporté.'],
        );

        $payload = json_decode((string) file_get_contents($this->importFile->getRealPath()), true);

        /*
         * Le refus est EXPLICITE et dit quoi faire. Un import silencieux qui ne crée rien laisse
         * l'administrateur relancer trois fois avant d'appeler quelqu'un.
         */
        if (! is_array($payload) || ! isset($payload['questions']) || ! is_array($payload['questions'])) {
            $this->addError('importFile', 'Ce fichier n’est pas un parcours exporté : il doit contenir une liste « questions ».');

            return;
        }

        $result = app(QuestionnairePortability::class)->import($this->trade, $payload);

        $this->importFile = null;
        unset($this->questions);

        $this->flash = sprintf(
            'Import terminé : %d question(s) créée(s), %d mise(s) à jour%s.',
            $result['created'],
            $result['updated'],
            $result['skipped'] ? sprintf(', %d ignorée(s)', count($result['skipped'])) : '',
        );
    }

    /**
     * Remet une version publiée en ligne.
     *
     * Figer des versions sans pouvoir y revenir ne sert qu'à constater les dégâts : une grille
     * tarifaire fautive partie en production se répare autrement à la main, question par question,
     * pendant que les clients commandent au mauvais prix.
     */
    public function restoreRevision(int $revisionId): void
    {
        if ($this->refusesWrite('publication')) {
            return;
        }

        $revision = $this->trade->formRevisions()->find($revisionId);

        if (! $revision) {
            return;
        }

        try {
            $new = app(TradeFormPublisher::class)->restore($revision, Auth::user());
            $this->flash = sprintf(
                'Version %d restaurée : elle est repartie en ligne sous le numéro %d. Les versions intermédiaires restent consultables.',
                $revision->version,
                $new->version,
            );
        } catch (ValidationException $e) {
            $this->flash = null;
            $this->addError('publication', collect($e->errors())->flatten()->implode(' '));
        }

        $this->refreshDerived();
    }

    /** L'historique des versions, la plus récente d'abord. */
    #[Computed]
    public function revisions()
    {
        return $this->trade->formRevisions()
            ->with('publishedBy')
            ->orderByDesc('version')
            ->limit(20)
            ->get();
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
        if ($this->refusesWrite()) {
            return;
        }

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
        if ($this->refusesWrite()) {
            return;
        }

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
