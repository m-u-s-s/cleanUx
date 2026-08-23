<?php

namespace App\Services\Recruitment;

use App\Mail\OrganizationInvitationMail;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\Organizations\OrganizationNotifier;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** LE RECRUTEMENT D'UNE SOCIÉTÉ PRESTATAIRE (E25). */
class RecruitmentService
{
    public function __construct(
        protected OrganizationNotifier $notifier,
    ) {}

    public function ouvrirUneOffre(
        int $organisationId,
        User $auteur,
        string $titre,
        ?int $tradeId = null,
        ?string $description = null,
    ): JobPosting {
        return JobPosting::query()->create([
            'organization_account_id' => $organisationId,
            'trade_id' => $tradeId,
            'reference' => JobPosting::genererUneReference(),
            'title' => $titre,
            'description' => $description,
            // On ne publie pas en créant : une offre à moitié écrite attire des candidatures à
            // moitié pertinentes, qu'il faudra trier quand même.
            'status' => JobPosting::STATUS_DRAFT,
            'created_by_user_id' => $auteur->id,
        ]);
    }

    /** @throws DomainException */
    public function publier(JobPosting $offre): JobPosting
    {
        if ($offre->status === JobPosting::STATUS_PUBLISHED) {
            return $offre;
        }

        if ($offre->status === JobPosting::STATUS_CLOSED) {
            throw new DomainException('Une offre fermée se rouvre en en créant une nouvelle.');
        }

        $offre->forceFill([
            'status' => JobPosting::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->save();

        return $offre->fresh();
    }

    public function fermer(JobPosting $offre): JobPosting
    {
        $offre->forceFill([
            // Fermée mais CONSERVÉE : les candidatures reçues restent lisibles, et c'est souvent
            // dans ce vivier qu'on repêche six mois plus tard.
            'status' => JobPosting::STATUS_CLOSED,
            'closed_at' => now(),
        ])->save();

        return $offre->fresh();
    }

    /**
     * Quelqu'un postule.
     *
     * @throws DomainException
     */
    public function postuler(
        JobPosting $offre,
        string $nom,
        string $email,
        ?string $telephone = null,
        ?string $message = null,
        ?User $utilisateur = null,
    ): JobApplication {
        if (! $offre->accepteDesCandidatures()) {
            // Un brouillon ferait répondre à une annonce non finie ; une offre fermée laisserait quelqu'un attendre une réponse qui ne viendra jamais.
            throw new DomainException('Cette offre n’accepte pas de candidature.');
        }

        $email = mb_strtolower(trim($email));

        $existante = JobApplication::query()
            ->where('job_posting_id', $offre->id)
            ->where('email', $email)
            ->first();

        if ($existante !== null) {
            // Un double clic ne doit pas produire deux candidatures que le tri devra départager à
            // la main : on rend la première.
            return $existante;
        }

        $candidature = JobApplication::query()->create([
            'job_posting_id' => $offre->id,
            'user_id' => $utilisateur?->id,
            'full_name' => trim($nom),
            'email' => $email,
            'phone' => $telephone,
            'message' => $message,
            'status' => JobApplication::STATUS_RECEIVED,
        ]);

        try {
            $this->notifier->notifierPorteursDe(
                organisationId: (int) $offre->organization_account_id,
                permission: 'recruitment.manage',
                titre: 'Candidature : '.$offre->title,
                corps: $candidature->full_name,
                donnees: ['job_application_id' => $candidature->id],
                cleIdempotence: 'recruitment:applied:'.$candidature->id,
            );
        } catch (\Throwable $e) {
            // La candidature existe : une notification qui échoue ne doit pas l'effacer.
            report($e);
        }

        return $candidature;
    }

    /** Retenir pour la suite — sans engagement : c'est l'embauche qui engage. */
    public function retenir(JobApplication $candidature, User $decideur): JobApplication
    {
        $candidature->forceFill([
            'status' => JobApplication::STATUS_SHORTLISTED,
            'decided_by_user_id' => $decideur->id,
            'decided_at' => now(),
        ])->save();

        return $candidature->fresh();
    }

    public function refuser(JobApplication $candidature, User $decideur, ?string $motif = null): JobApplication
    {
        $candidature->forceFill([
            // Conservée : un refus effacé, c'est la même candidature qui revient dans six mois sans
            // que personne ne se souvienne pourquoi elle avait été écartée.
            'status' => JobApplication::STATUS_REJECTED,
            'decided_by_user_id' => $decideur->id,
            'decided_at' => now(),
            'decision_note' => $motif,
        ])->save();

        return $candidature->fresh();
    }

    /**
     * Embaucher — c'est-à-dire ÉMETTRE L'INVITATION. Un même geste, pas deux écrans.
     *
     * @throws DomainException
     */
    public function embaucher(JobApplication $candidature, User $decideur, string $role = 'worker'): JobApplication
    {
        if ($candidature->status === JobApplication::STATUS_HIRED) {
            // Rejouable : un double clic ne doit pas émettre deux jetons pour la même personne.
            return $candidature;
        }

        $offre = $candidature->posting;

        if ($offre === null) {
            throw new DomainException('Cette candidature n’est rattachée à aucune offre.');
        }

        return DB::transaction(function () use ($candidature, $offre, $decideur, $role) {
            $invitation = OrganizationInvitation::query()->updateOrCreate(
                [
                    'organization_account_id' => $offre->organization_account_id,
                    'email' => $candidature->email,
                    'status' => 'pending',
                ],
                [
                    'role' => $role,
                    'invited_by' => $decideur->id,
                    'token' => OrganizationInvitation::genererJeton(),
                    'expires_at' => now()->addDays(14),
                ],
            );

            $candidature->forceFill([
                'status' => JobApplication::STATUS_HIRED,
                'decided_by_user_id' => $decideur->id,
                'decided_at' => now(),
                // Le lien entre l'annonce et la recrue : sans lui, on ne saura jamais quelle offre
                // a produit qui.
                'organization_invitation_id' => $invitation->id,
            ])->save();

            try {
                Mail::to($candidature->email)->send(new OrganizationInvitationMail(
                    $invitation,
                    route('organization.invitations.accept', $invitation->token),
                ));
            } catch (\Throwable $e) {
                // SOFT-FAIL : l'invitation est enregistrée et se renvoie.
                Log::warning('[recruitment] envoi de l’invitation impossible', [
                    'invitation_id' => $invitation->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $candidature->fresh('invitation');
        });
    }
}
