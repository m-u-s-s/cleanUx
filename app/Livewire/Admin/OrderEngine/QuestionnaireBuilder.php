<?php

namespace App\Livewire\Admin\OrderEngine;

use App\Livewire\OrderEngine\QuestionRenderer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Services\OrderEngine\CatalogArchiver;
use App\Services\OrderEngine\PriceBreakdown;
use App\Services\OrderEngine\PricingEngine;
use App\Services\OrderEngine\QuestionnaireValidator;
use App\Support\Domain\OrderMode;
use App\Support\Domain\PriceImpactMode;
use App\Support\Domain\QuestionType;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            ->with(['options', 'conditions'])
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
        unset($this->questions, $this->issues, $this->canPublish, $this->quote);
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
