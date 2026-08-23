<?php

namespace App\Livewire\Admin\CancellationV2;

use App\Models\CancellationQuestion;
use App\Models\CancellationQuestionOption;
use App\Services\Cancellation\CancellationQuestionnaireService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** LE QUESTIONNAIRE D'ANNULATION, ADMINISTRÉ DEPUIS LE WEB. */
class QuestionnaireCenter extends Component
{
    use EnforcesAdminAccess;

    #[Locked]
    public ?string $erreur = null;

    public string $nouvelleQuestionCode = '';

    public string $nouvelleQuestionLabel = '';

    public string $nouvelleQuestionAudience = 'client';

    public string $nouvelleQuestionMoteur = '';

    public function basculerQuestion(int $questionId): void
    {
        $this->erreur = null;

        $question = CancellationQuestion::query()->findOrFail($questionId);

        try {
            $this->service()->basculerQuestion($question, ! $question->is_active);
        } catch (DomainException $e) {
            // LE MOTIF S'AFFICHE : « activez au moins une option d'abord » dit quoi faire, là où un
            // interrupteur qui ne bouge pas ferait cliquer trois fois.
            $this->erreur = $e->getMessage();
        }
    }

    public function basculerOption(int $optionId): void
    {
        $this->erreur = null;

        $option = CancellationQuestionOption::query()->findOrFail($optionId);

        try {
            $this->service()->basculerOption($option, ! $option->is_active);
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function retirerQuestion(int $questionId): void
    {
        $this->erreur = null;

        $this->service()->retirerQuestion(CancellationQuestion::query()->findOrFail($questionId));
    }

    public function ajouterQuestion(): void
    {
        $this->erreur = null;

        $this->validate([
            'nouvelleQuestionCode' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'nouvelleQuestionLabel' => ['required', 'string', 'max:191'],
            'nouvelleQuestionAudience' => ['required', 'in:client,provider,both'],
            'nouvelleQuestionMoteur' => ['nullable', 'in:domicile,horaire,vehicule'],
        ]);

        try {
            $this->service()->ajouterQuestion([
                'code' => $this->nouvelleQuestionCode,
                'label' => $this->nouvelleQuestionLabel,
                'audience' => $this->nouvelleQuestionAudience,
                'engine' => $this->nouvelleQuestionMoteur ?: null,
                // NAÎT INACTIVE, et c'est délibéré : une question sans réponse possible n'a rien à
                // faire dans un questionnaire en production. On l'active une fois ses options
                // posées, et le service refuse tant qu'elle n'en a aucune.
                'is_active' => false,
            ]);
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        $this->reset(['nouvelleQuestionCode', 'nouvelleQuestionLabel', 'nouvelleQuestionMoteur']);
    }

    public function render(): View
    {
        return view('livewire.admin.cancellation-v2.questionnaire-center', [
            'questions' => CancellationQuestion::query()
                ->with(['options' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
                ->orderBy('audience')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    private function service(): CancellationQuestionnaireService
    {
        return app(CancellationQuestionnaireService::class);
    }
}
