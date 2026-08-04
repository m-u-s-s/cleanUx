<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Models\User;
use App\Services\Catalog\CatalogOrdering;
use App\Services\OrderEngine\QuestionnaireValidator;
use App\Services\OrderEngine\TradeFormPublisher;
use App\Support\Domain\QuestionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Le parcours de questions d'un métier, servi à l'application mobile.
 *
 * POURQUOI CE CONTRÔLEUR EXISTE. Le constructeur web est le seul endroit où l'on écrit ce que le
 * client verra — les questions, leurs réponses, et le PRIX que chaque réponse ajoute. Le mobile
 * n'y avait aucun accès : un métier créé en déplacement restait sans parcours, donc impubliable, et
 * rien à l'écran ne le disait.
 *
 * IL NE RÉIMPLÉMENTE AUCUNE RÈGLE. La validation vient de `QuestionnaireValidator`, la publication
 * de `TradeFormPublisher`, l'ordre de `CatalogOrdering` — les mêmes services que le web. Deux
 * chemins vers la même table produiraient sinon deux verdicts selon la porte empruntée.
 *
 * CE QU'IL NE SERT PAS, et c'est délibéré : traductions, révisions, import/export, duplication vers
 * un autre métier. Ce sont des gestes de bureau, pas de terrain, et chacun demanderait son écran.
 */
class JourneyBuilderController extends Controller
{
    /**
     * Un compte en LECTURE SEULE ne touche pas au parcours.
     *
     * `api_admin` s'arrête à « est-ce un administrateur » : la même règle que le web doit valoir
     * ici, sinon elle dépend de la porte empruntée.
     */
    private function refuseLecteurSeul(): ?JsonResponse
    {
        $user = request()->user();

        if ($user instanceof User && $user->isReadOnlyAdmin()) {
            return response()->json([
                'ok' => false,
                'error' => 'forbidden_readonly',
                'error_code' => 'forbidden_readonly',
            ], 403);
        }

        return null;
    }

    /** Le parcours complet, avec le verdict de publication. */
    public function show(Trade $trade, QuestionnaireValidator $validateur): JsonResponse
    {
        $questions = Question::query()
            ->where('trade_id', $trade->id)
            ->where('is_active', true)
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Question $question) => [
                'id' => $question->id,
                'code' => $question->code,
                'label' => $question->label,
                'help_text' => $question->help_text,
                'type' => $question->type,
                'is_required' => (bool) $question->is_required,
                'sort_order' => (int) $question->sort_order,
                // Le prix porté par la QUESTION — mode et coefficient — distinct de celui des
                // options : l'un s'applique à une valeur numérique, l'autre à un choix.
                'pricing' => $question->pricing,
                'options' => $question->options->map(fn (QuestionOption $option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'value' => $option->value,
                    'price_modifier_cents' => (int) $option->price_modifier_cents,
                    'price_multiplier' => $option->price_multiplier,
                    'duration_modifier_min' => (int) $option->duration_modifier_min,
                    'is_default' => (bool) $option->is_default,
                ])->values()->all(),
            ]);

        return response()->json([
            'ok' => true,
            'trade' => ['id' => $trade->id, 'name' => $trade->name, 'slug' => $trade->slug],
            'data' => $questions->values()->all(),
            /*
             * Le verdict, servi AVEC le parcours. Sans lui, on règle des questions sans savoir si
             * l'ensemble partira — et l'écran web, lui, le dit en permanence.
             */
            'publication' => [
                'can_publish' => $validateur->canPublish($trade),
                'issues' => $validateur->inspect($trade),
            ],
        ]);
    }

    public function storeQuestion(Request $request, Trade $trade): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        $valide = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            // Le code est UNIQUE PAR MÉTIER, pas globalement : deux métiers peuvent poser une
            // question « surface », et les commandes citent le couple.
            'code' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('questions', 'code')->where('trade_id', $trade->id),
            ],
            'type' => ['required', Rule::in(QuestionType::all())],
            'help_text' => ['nullable', 'string', 'max:500'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $question = Question::create($valide + [
            'trade_id' => $trade->id,
            'is_active' => true,
            // En fin de parcours : une question neuve n'a pas à passer devant celles qu'on a déjà
            // ordonnées, et l'ordre est le parcours.
            'sort_order' => (int) Question::where('trade_id', $trade->id)->max('sort_order') + 1,
        ]);

        return response()->json(['ok' => true, 'data' => ['id' => $question->id]], 201);
    }

    public function updateQuestion(Request $request, Question $question): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        $valide = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'help_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        /*
         * LE CODE N'EST PAS MODIFIABLE ICI, et c'est volontaire. Il est cité par les commandes
         * déjà passées et par les conditions d'affichage : le changer romprait ces liens en
         * silence. Le web le verrouille dès qu'une commande le cite ; le mobile ne l'offre pas.
         */
        $question->forceFill($valide)->save();

        return response()->json(['ok' => true, 'data' => ['id' => $question->id]]);
    }

    public function moveQuestion(Request $request, Question $question, CatalogOrdering $ordre): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        $sens = (int) $request->validate([
            'direction' => ['required', 'integer', 'in:-1,1'],
        ])['direction'];

        $ordre->deplacer(
            Question::query()->where('trade_id', $question->trade_id)->where('is_active', true),
            $question->id,
            $sens,
        );

        return response()->json(['ok' => true]);
    }

    public function destroyQuestion(Question $question): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        // Désactivée, pas supprimée : les commandes passées citent son code, et une question
        // effacée rendrait leur détail illisible.
        $question->forceFill(['is_active' => false])->save();

        return response()->json(['ok' => true]);
    }

    public function storeOption(Request $request, Question $question): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        $valide = $request->validate([
            'label' => ['required', 'string', 'max:180'],
            'price_modifier_euros' => ['nullable', 'string', 'max:20'],
        ]);

        $option = QuestionOption::create([
            'question_id' => $question->id,
            'label' => $valide['label'],
            'value' => 'option_'.(QuestionOption::where('question_id', $question->id)->count() + 1),
            'price_modifier_cents' => $this->centimes($valide['price_modifier_euros'] ?? null),
            'sort_order' => (int) QuestionOption::where('question_id', $question->id)->max('sort_order') + 1,
            // Jamais par défaut d'office : deux défauts feraient dépendre l'écran client de l'ordre
            // de tri, et le validateur refuserait la publication.
            'is_default' => ! QuestionOption::where('question_id', $question->id)->exists(),
        ]);

        return response()->json(['ok' => true, 'data' => ['id' => $option->id]], 201);
    }

    public function updateOption(Request $request, QuestionOption $option): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        $valide = $request->validate([
            'label' => ['sometimes', 'string', 'max:180'],
            'price_modifier_euros' => ['sometimes', 'nullable', 'string', 'max:20'],
            'price_multiplier' => ['sometimes', 'nullable', 'numeric', 'min:0.1', 'max:10'],
            'duration_modifier_min' => ['sometimes', 'nullable', 'integer', 'min:-1440', 'max:1440'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $changements = [];

        foreach (['label', 'price_multiplier', 'duration_modifier_min'] as $champ) {
            if (array_key_exists($champ, $valide)) {
                $changements[$champ] = $valide[$champ];
            }
        }

        if (array_key_exists('price_modifier_euros', $valide)) {
            $changements['price_modifier_cents'] = $this->centimes($valide['price_modifier_euros']);
        }

        if (! empty($valide['is_default'])) {
            // Un seul défaut par question.
            QuestionOption::where('question_id', $option->question_id)->update(['is_default' => false]);
            $changements['is_default'] = true;
        }

        $option->forceFill($changements)->save();

        return response()->json(['ok' => true, 'data' => ['id' => $option->id]]);
    }

    public function destroyOption(QuestionOption $option): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        $option->delete();

        return response()->json(['ok' => true]);
    }

    public function publish(Trade $trade, TradeFormPublisher $publieur): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        try {
            $revision = $publieur->publish($trade, request()->user());
        } catch (ValidationException $e) {
            /*
             * 409 et non 422 : le parcours n'est pas mal saisi, il n'est pas PRÊT. La même requête
             * réussira quand les manques seront comblés, et les motifs disent lesquels.
             */
            return response()->json([
                'ok' => false,
                'error' => 'not_publishable',
                'error_code' => 'not_publishable',
                'reasons' => collect($e->errors())->flatten()->values()->all(),
            ], 409);
        }

        return response()->json(['ok' => true, 'data' => ['version' => $revision->version]]);
    }

    /**
     * Des euros saisis à la main vers des centimes en base.
     *
     * La virgule est acceptée : c'est la façon française d'écrire un prix. Sans cette conversion,
     * « 150 » deviendrait 150 centimes — un supplément de 1,50 € que personne ne remarque avant la
     * première facture.
     */
    private function centimes(mixed $saisie): int
    {
        $texte = trim(str_replace([' ', ','], ['', '.'], (string) $saisie));

        return $texte === '' || ! is_numeric($texte) ? 0 : (int) round(((float) $texte) * 100);
    }
}
