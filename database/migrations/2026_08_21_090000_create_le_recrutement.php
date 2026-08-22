<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE RECRUTEMENT D'UNE SOCIÉTÉ PRESTATAIRE (E25).
 *
 * LA DERNIÈRE ÉTAPE EXISTE DÉJÀ, ET C'EST TOUT L'INTÉRÊT. L'invitation à jeton conclut le
 * recrutement depuis longtemps : elle crée le compte, l'adhésion, le rôle. Ce qui manquait est
 * AVANT — l'offre, les candidatures, le tri. Une société qui recrute passait donc par une annonce
 * ailleurs, un échange de courriels, puis revenait ici pour envoyer une invitation à quelqu'un dont
 * la plateforme n'avait jamais entendu parler. La moitié du recrutement se faisait hors de l'outil,
 * et le lien entre les deux n'existait dans aucune donnée.
 *
 * QUATRE ÉTAPES, ET C'EST ASSEZ. `received` → `shortlisted` → `hired` | `rejected`. Un pipeline plus
 * fin — entretien téléphonique, entretien technique, période d'essai — décrit le processus d'une
 * grande entreprise, pas celui d'une société de dix personnes qui embauche trois fois par an. Les
 * étapes qu'on n'utilise pas ne se remplissent pas, et un pipeline à moitié rempli ment.
 *
 * LES CANDIDATS NE SONT PAS DES UTILISATEURS. Quelqu'un qui postule n'a pas de compte, et exiger
 * qu'il en crée un avant de postuler diviserait les candidatures par cinq. Le nom, le courriel et le
 * téléphone vivent donc sur la candidature — et `user_id` reste nullable, rempli seulement si la
 * personne se trouve déjà être quelqu'un de la plateforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('job_postings')) {
            Schema::create('job_postings', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('organization_account_id');
                $table->unsignedBigInteger('trade_id')->nullable();
                $table->unsignedBigInteger('provider_agency_id')->nullable();

                $table->string('reference', 40)->unique();
                $table->string('title', 160);
                $table->text('description')->nullable();

                $table->string('employment_type', 30)->default('full_time');
                $table->string('city', 120)->nullable();

                // Une fourchette, pas un chiffre : une offre sans indication de salaire fait fuir
                // les candidats qualifiés, et un chiffre unique n'existe pas dans la réalité.
                $table->unsignedBigInteger('salary_min_cents')->nullable();
                $table->unsignedBigInteger('salary_max_cents')->nullable();

                // draft → published → closed
                $table->string('status', 20)->default('draft');

                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('closed_at')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                // Noms courts : MySQL refuse un index au-delà de 64 caractères.
                $table->index(['organization_account_id', 'status'], 'job_postings_org_status_idx');

                $table->foreign('organization_account_id')->references('id')->on('organization_accounts')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('job_applications')) {
            Schema::create('job_applications', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('job_posting_id');

                /*
                 * LE CANDIDAT N'EST PAS UN UTILISATEUR. Exiger un compte avant de postuler
                 * diviserait les candidatures par cinq ; `user_id` n'est rempli que si la personne
                 * se trouve déjà être quelqu'un de la plateforme.
                 */
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('full_name', 160);
                $table->string('email', 190);
                $table->string('phone', 40)->nullable();

                $table->text('message')->nullable();
                $table->string('cv_path', 255)->nullable();

                // received → shortlisted → hired | rejected
                $table->string('status', 20)->default('received');

                $table->unsignedBigInteger('decided_by_user_id')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_note')->nullable();

                // Le lien avec l'invitation à jeton — la seule chose qui transforme une candidature
                // en collègue.
                $table->unsignedBigInteger('organization_invitation_id')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['job_posting_id', 'status'], 'job_applications_posting_status_idx');
                // Une même personne ne postule qu'une fois à une même offre : sans cette contrainte,
                // un double clic produit deux candidatures que le tri devra départager à la main.
                $table->unique(['job_posting_id', 'email'], 'job_applications_posting_email_uq');

                $table->foreign('job_posting_id')->references('id')->on('job_postings')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_postings');
    }
};
