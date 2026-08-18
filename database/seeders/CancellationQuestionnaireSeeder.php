<?php

namespace Database\Seeders;

use App\Models\CancellationExemptReason;
use App\Models\CancellationPolicy;
use App\Models\CancellationQuestion;
use App\Models\CancellationQuestionOption;
use Illuminate\Database\Seeder;

/**
 * LE QUESTIONNAIRE D'ANNULATION PAR DÉFAUT.
 *
 * ── CE QU'IL REMPLACE ────────────────────────────────────────────────────────────────────────
 *
 * Un champ de texte libre. Un champ libre ne se compte pas, ne se compare pas, ne déclenche aucun
 * palier — et ne peut donc jamais valoir exemption. Le moteur d'annulation attendait un `reason_code`
 * que personne ne lui a jamais donné.
 *
 * ── LE PRINCIPE DE CHAQUE OPTION ─────────────────────────────────────────────────────────────
 *
 * Vérifiable, ou engageante. Rien d'autre. « Le prestataire est en retard » se mesure sur
 * `scheduled_at` et le statut réel ; « le prestataire m'a demandé d'annuler » n'exonère pas par
 * générosité mais parce que c'est le SEUL moyen d'apprendre ce qui se dit sur le palier.
 *
 * ── DEUX QUESTIONS CÔTÉ PRESTATAIRE, ET C'EST VOULU ──────────────────────────────────────────
 *
 * « Le travail ne correspond pas » n'a de sens que là où il existe un devis à réviser : elle vit
 * donc sur une question réservée au moteur à domicile, et disparaît pour un chauffeur. Le
 * questionnaire ne montre jamais une issue qui mènerait à un refus.
 */
class CancellationQuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        $client = CancellationPolicy::query()->where('code', 'default_client')->first();
        $prestataire = CancellationPolicy::query()->where('code', 'default_provider')->first();

        if (! $client || ! $prestataire) {
            $this->command?->warn('Politiques d’annulation absentes : jouez d’abord CancellationPoliciesSeeder.');

            return;
        }

        $exempts = $this->motifsExemptes($client, $prestataire);

        $this->questionClient($exempts);
        $this->questionPrestataire($exempts);
        $this->questionPrestataireDomicile();

        $this->command?->info('Questionnaire d’annulation semé : 3 questions, 11 réponses.');
    }

    /**
     * LES MOTIFS QUI EXONÈRENT — créés ici parce que le questionnaire en dépend.
     *
     * `max_per_user_per_30d` est la règle « pas la première fois, mais si c'est fréquent » : au-delà,
     * l'exemption cesse de jouer et le palier normal s'applique, sans que le motif disparaisse du
     * dossier.
     *
     * Les deux motifs « l'autre m'a demandé d'annuler » n'ont AUCUN plafond, et c'est délibéré :
     * plafonner le piège à entente le rendrait coûteux à dire, et personne ne le dirait plus.
     *
     * @return array<string, CancellationExemptReason>
     */
    private function motifsExemptes(CancellationPolicy $client, CancellationPolicy $prestataire): array
    {
        $definitions = [
            ['policy' => $client, 'reason_code' => 'provider_late', 'label' => 'Prestataire en retard', 'requires_proof' => false, 'max_per_user_per_30d' => null],
            ['policy' => $client, 'reason_code' => 'provider_asked_cancel', 'label' => 'Le prestataire m’a demandé d’annuler', 'requires_proof' => false, 'max_per_user_per_30d' => null],
            ['policy' => $prestataire, 'reason_code' => 'provider_unable', 'label' => 'Empêchement du prestataire (panne, maladie, accident)', 'requires_proof' => true, 'max_per_user_per_30d' => 2],
            ['policy' => $prestataire, 'reason_code' => 'address_unreachable', 'label' => 'Adresse introuvable ou inaccessible', 'requires_proof' => false, 'max_per_user_per_30d' => 3],
            ['policy' => $prestataire, 'reason_code' => 'client_asked_cancel', 'label' => 'Le client m’a demandé d’annuler', 'requires_proof' => false, 'max_per_user_per_30d' => null],
        ];

        $motifs = [];

        foreach ($definitions as $definition) {
            $policy = $definition['policy'];
            unset($definition['policy']);

            $motifs[$definition['reason_code']] = CancellationExemptReason::query()->updateOrCreate(
                ['policy_id' => $policy->id, 'reason_code' => $definition['reason_code']],
                $definition + ['is_active' => true],
            );
        }

        return $motifs;
    }

    /**
     * @param  array<string, CancellationExemptReason>  $exempts
     */
    private function questionClient(array $exempts): void
    {
        $question = $this->question([
            'code' => 'client_cancel_why',
            'audience' => CancellationQuestion::AUDIENCE_CLIENT,
            'label' => 'Que se passe-t-il ?',
            'help_text' => 'Votre réponse décide des frais : dites ce qui s’est réellement passé.',
            'sort_order' => 10,
        ]);

        $this->options($question, [
            [
                'code' => 'client_provider_late',
                'label' => 'Le prestataire est en retard',
                // VÉRIFIÉE : l'option n'apparaît que si l'heure prévue est réellement dépassée et
                // que l'intervention n'a pas démarré. Sinon elle n'est pas proposée du tout.
                'verification' => CancellationQuestionOption::VERIF_RETARD,
                'exempt_reason_id' => $exempts['provider_late']->id,
                'sort_order' => 10,
            ],
            [
                'code' => 'client_no_longer_needed',
                'label' => 'Je n’ai plus besoin de ce service',
                'sort_order' => 20,
            ],
            [
                'code' => 'client_wrong_request',
                'label' => 'Je me suis trompé dans ma demande',
                'sort_order' => 30,
            ],
            [
                /*
                 * LE PIÈGE À ENTENTE, et il ne coûte rien à poser.
                 *
                 * Un client à qui l'on propose un arrangement en liquide n'a aucune raison de mentir
                 * ici : cocher lui évite les frais. C'est le seul endroit d'où la plateforme peut
                 * apprendre ce qui se dit sur le palier.
                 */
                'code' => 'client_provider_asked',
                'label' => 'Le prestataire m’a demandé d’annuler',
                'exempt_reason_id' => $exempts['provider_asked_cancel']->id,
                'collusion_signal' => true,
                'sort_order' => 40,
            ],
            [
                'code' => 'client_other',
                'label' => 'Autre',
                'requires_text' => true,
                'outcome' => CancellationQuestionOption::ISSUE_REVUE,
                'sort_order' => 90,
            ],
        ]);
    }

    /**
     * @param  array<string, CancellationExemptReason>  $exempts
     */
    private function questionPrestataire(array $exempts): void
    {
        $question = $this->question([
            'code' => 'provider_cancel_why',
            'audience' => CancellationQuestion::AUDIENCE_PRESTATAIRE,
            'label' => 'Pourquoi annulez-vous ?',
            'help_text' => 'Un motif répété demande un justificatif.',
            'sort_order' => 10,
        ]);

        $this->options($question, [
            [
                'code' => 'provider_unable',
                'label' => 'Je ne peux pas m’y rendre (panne, maladie, accident)',
                'exempt_reason_id' => $exempts['provider_unable']->id,
                'requires_text' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'provider_address_unreachable',
                'label' => 'Adresse introuvable ou inaccessible',
                // VÉRIFIÉE PAR LA TRACE : quelqu'un qui n'a pas bougé n'a pas pu constater qu'une
                // adresse était introuvable.
                'verification' => CancellationQuestionOption::VERIF_DEPLACEMENT,
                'exempt_reason_id' => $exempts['address_unreachable']->id,
                'sort_order' => 20,
            ],
            [
                'code' => 'provider_client_unreachable',
                'label' => 'Le client ne répond pas',
                'verification' => CancellationQuestionOption::VERIF_CLIENT_INJOIGNABLE,
                // AIGUILLAGE : le no-show existe déjà et s'ouvre après un délai serveur. Annuler ici
                // ferait perdre au prestataire l'indemnité que le no-show lui garantit.
                'outcome' => CancellationQuestionOption::ISSUE_VERS_ABSENCE,
                'sort_order' => 30,
            ],
            [
                'code' => 'provider_client_asked',
                'label' => 'Le client m’a demandé d’annuler',
                'exempt_reason_id' => $exempts['client_asked_cancel']->id,
                'collusion_signal' => true,
                'sort_order' => 40,
            ],
        ]);
    }

    /**
     * LA QUESTION QUI N'EXISTE QUE LÀ OÙ IL Y A UN DEVIS À RÉVISER.
     *
     * C'est le point le plus important du questionnaire prestataire : celui qui veut partir parce
     * que le chantier est trop gros ne doit PAS annuler. On le lui montre au moment exact où il
     * s'apprête à faire le mauvais geste — et l'option disparaît pour un chauffeur, chez qui elle
     * ne mènerait qu'à un refus.
     */
    private function questionPrestataireDomicile(): void
    {
        $question = $this->question([
            'code' => 'provider_scope_check',
            'audience' => CancellationQuestion::AUDIENCE_PRESTATAIRE,
            'engine' => 'domicile',
            'label' => 'Le travail correspond-il à l’annonce ?',
            'help_text' => 'S’il est bien plus gros que prévu, ce n’est pas une annulation.',
            'sort_order' => 5,
        ]);

        $this->options($question, [
            [
                'code' => 'provider_scope_mismatch',
                'label' => 'Non, le travail ne correspond pas du tout à l’annonce',
                'outcome' => CancellationQuestionOption::ISSUE_VERS_DEVIS,
                'requires_text' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'provider_scope_too_big_alone',
                'label' => 'Le chantier est trop gros pour moi seul',
                'outcome' => CancellationQuestionOption::ISSUE_VERS_RENFORT,
                'sort_order' => 20,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function question(array $donnees): CancellationQuestion
    {
        return CancellationQuestion::query()->updateOrCreate(
            ['code' => $donnees['code']],
            $donnees + ['is_active' => true],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $options
     */
    private function options(CancellationQuestion $question, array $options): void
    {
        foreach ($options as $option) {
            CancellationQuestionOption::query()->updateOrCreate(
                ['cancellation_question_id' => $question->id, 'code' => $option['code']],
                $option + ['is_active' => true],
            );
        }
    }
}
