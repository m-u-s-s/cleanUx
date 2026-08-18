<?php

namespace App\Services\Cancellation;

use App\Models\Booking;
use App\Models\CancellationQuestion;
use App\Models\CancellationQuestionOption;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Domain\MissionEngine;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * LE QUESTIONNAIRE D'ANNULATION — servi aux écrans, administré depuis la console.
 *
 * ── CETTE CLASSE EST LA SEULE PORTE D'ÉCRITURE ───────────────────────────────────────────────
 *
 * Règle du dépôt pour la console d'administration : une action passe par le service du domaine,
 * jamais par une écriture de colonne. Écrire `is_active = false` à la main produirait l'état sans
 * ses effets — ici, sans le journal, et sans la garantie qu'une question active garde au moins une
 * réponse possible.
 *
 * ── RIEN NE SE SUPPRIME VRAIMENT ─────────────────────────────────────────────────────────────
 *
 * `retirer()` fait un `delete()` doux. Une annulation d'il y a six mois porte le `reason_code`
 * d'une option qu'un administrateur a peut-être retirée depuis : le dossier doit rester lisible.
 * Et le `code` ne se réutilise jamais — recyclé, il ferait relire un dossier ancien avec le sens
 * d'aujourd'hui.
 */
class CancellationQuestionnaireService
{
    public function __construct(
        private readonly CancellationAnswerVerifier $verificateur,
    ) {}

    /**
     * CE QUE L'ÉCRAN AFFICHE — les questions de cette audience, et les seules options SOUTENUES.
     *
     * Une option dont la vérification échoue n'apparaît pas. La proposer puis la refuser se lirait
     * comme une panne, et la personne recommencerait.
     *
     * @return list<array<string, mixed>>
     */
    public function pour(User $acteur, Booking $booking, string $audience): array
    {
        $moteur = MissionEngine::pourReservation($booking);

        return CancellationQuestion::query()
            ->pourAudience($audience)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('engine')->orWhere('engine', $moteur))
            ->with(['options' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CancellationQuestion $question) => [
                'code' => $question->code,
                'label' => $question->label,
                'help_text' => $question->help_text,
                'options' => $question->options
                    ->filter(fn (CancellationQuestionOption $o) => $this->verificateur->estSoutenue($o, $booking))
                    ->values()
                    ->map(fn (CancellationQuestionOption $o) => [
                        'code' => $o->code,
                        'label' => $o->label,
                        'outcome' => $o->outcome,
                        'requires_text' => $o->requires_text,
                        'requires_proof' => $o->requires_proof,
                        // L'écran doit savoir qu'il ne va PAS annuler : « le travail ne correspond
                        // pas » renvoie vers le nouveau devis, pas vers une annulation.
                        'redirects' => $o->estUnAiguillage(),
                    ])->all(),
            ])
            // Une question dont aucune option n'est soutenue ne se pose pas.
            ->filter(fn (array $q) => $q['options'] !== [])
            ->values()
            ->all();
    }

    /** L'option derrière un code reçu d'un écran — active, et seulement active. */
    public function option(string $code): ?CancellationQuestionOption
    {
        return CancellationQuestionOption::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    // ── ADMINISTRATION ───────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $donnees
     *
     * @throws DomainException
     */
    public function ajouterQuestion(array $donnees): CancellationQuestion
    {
        $code = trim((string) ($donnees['code'] ?? ''));

        if ($code === '') {
            throw new DomainException('Une question porte un code stable.');
        }

        // `withTrashed()` : un code retiré reste PRIS. Le réutiliser ferait relire un dossier
        // ancien avec le sens d'aujourd'hui.
        if (CancellationQuestion::withTrashed()->where('code', $code)->exists()) {
            throw new DomainException('Ce code est déjà pris, y compris par une question retirée.');
        }

        $question = CancellationQuestion::query()->create($donnees + ['code' => $code]);

        ActivityLogger::log('cancellation_question.created', $question, ['code' => $code]);

        return $question;
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function modifierQuestion(CancellationQuestion $question, array $donnees): CancellationQuestion
    {
        // Le CODE ne se modifie pas : il vit dans les dossiers déjà clos.
        unset($donnees['code']);

        $question->update($donnees);

        ActivityLogger::log('cancellation_question.updated', $question, ['champs' => array_keys($donnees)]);

        return $question->fresh();
    }

    /**
     * @throws DomainException
     */
    public function basculerQuestion(CancellationQuestion $question, bool $active): CancellationQuestion
    {
        if ($active && $question->options()->where('is_active', true)->doesntExist()) {
            throw new DomainException(
                'Cette question n’a aucune réponse possible : activez au moins une option d’abord.'
            );
        }

        $question->update(['is_active' => $active]);

        ActivityLogger::log('cancellation_question.toggled', $question, ['is_active' => $active]);

        return $question->fresh();
    }

    public function retirerQuestion(CancellationQuestion $question): void
    {
        DB::transaction(function () use ($question) {
            $question->options()->update(['is_active' => false]);
            $question->options()->delete();
            $question->update(['is_active' => false]);
            $question->delete();
        });

        ActivityLogger::log('cancellation_question.removed', $question, ['code' => $question->code]);
    }

    /**
     * @param  array<string, mixed>  $donnees
     *
     * @throws DomainException
     */
    public function ajouterOption(CancellationQuestion $question, array $donnees): CancellationQuestionOption
    {
        $code = trim((string) ($donnees['code'] ?? ''));

        if ($code === '') {
            throw new DomainException('Une réponse porte un code stable : c’est lui qui vit dans le dossier.');
        }

        if (CancellationQuestionOption::withTrashed()->where('code', $code)->exists()) {
            throw new DomainException('Ce code de réponse est déjà pris, y compris par une option retirée.');
        }

        $this->assertIssueCoherente($donnees['outcome'] ?? CancellationQuestionOption::ISSUE_ANNULER, $question);

        $option = $question->options()->create($donnees + ['code' => $code]);

        ActivityLogger::log('cancellation_option.created', $option, ['code' => $code]);

        return $option;
    }

    /**
     * @param  array<string, mixed>  $donnees
     *
     * @throws DomainException
     */
    public function modifierOption(CancellationQuestionOption $option, array $donnees): CancellationQuestionOption
    {
        unset($donnees['code']);

        if (isset($donnees['outcome'])) {
            $this->assertIssueCoherente((string) $donnees['outcome'], $option->question);
        }

        $option->update($donnees);

        ActivityLogger::log('cancellation_option.updated', $option, ['champs' => array_keys($donnees)]);

        return $option->fresh();
    }

    /**
     * @throws DomainException
     */
    public function basculerOption(CancellationQuestionOption $option, bool $active): CancellationQuestionOption
    {
        $question = $option->question;

        /*
         * ON NE DÉSACTIVE PAS LA DERNIÈRE RÉPONSE D'UNE QUESTION ACTIVE.
         *
         * Le questionnaire afficherait une question sans aucune case à cocher, et l'annulation
         * deviendrait impossible pour tout le monde — un blocage total produit par un geste
         * d'administration qui semblait anodin.
         */
        if (! $active
            && $question !== null
            && $question->is_active
            && $question->options()->where('is_active', true)->where('id', '!=', $option->id)->doesntExist()) {
            throw new DomainException(
                'C’est la dernière réponse possible de cette question : désactivez la question d’abord.'
            );
        }

        $option->update(['is_active' => $active]);

        ActivityLogger::log('cancellation_option.toggled', $option, ['is_active' => $active]);

        return $option->fresh();
    }

    /**
     * @throws DomainException
     */
    public function retirerOption(CancellationQuestionOption $option): void
    {
        $this->basculerOption($option, false);

        $option->delete();

        ActivityLogger::log('cancellation_option.removed', $option, ['code' => $option->code]);
    }

    /**
     * UNE ISSUE QUI RENVOIE VERS UN OUTIL QUI N'EXISTE PAS SUR CE MOTEUR EST UNE IMPASSE.
     *
     * « Le travail ne correspond pas » renvoie vers le nouveau devis — qui n'existe que sur le
     * moteur à domicile. Poser cette option sur une question réservée aux courses enverrait un
     * chauffeur vers un écran qui lui répondra non.
     *
     * @throws DomainException
     */
    private function assertIssueCoherente(string $issue, ?CancellationQuestion $question): void
    {
        if ($issue !== CancellationQuestionOption::ISSUE_VERS_DEVIS) {
            return;
        }

        $moteur = $question?->engine;

        if ($moteur !== null && ! MissionEngine::accepteLeNouveauDevis($moteur)) {
            throw new DomainException(
                'Le nouveau devis n’existe pas sur ce moteur : cette réponse mènerait à un refus.'
            );
        }
    }
}
