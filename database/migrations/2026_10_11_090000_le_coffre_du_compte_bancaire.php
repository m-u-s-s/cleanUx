<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE COMPTE QUI REÇOIT LES COMMISSIONS — dans un coffre, pas dans un formulaire.
 *
 * Un IBAN n'est pas un secret au sens d'un mot de passe : il figure sur chaque facture émise.
 * Mais la LIGNE qui dit « voici où va l'argent de la plateforme » en est un : la changer, c'est
 * détourner tous les encaissements à venir. C'est ce changement qu'on protège, pas la lecture.
 *
 * TROIS CHOSES TIENNENT CE COFFRE :
 *   — les valeurs sont CHIFFRÉES au repos ; une copie de la base ne les rend pas ;
 *   — un CODE est exigé pour ouvrir et pour changer, distinct du mot de passe de connexion ;
 *   — CHAQUE VERSION EST CONSERVÉE. On ne remplace jamais : on ajoute, et l'ancienne se ferme.
 *     Sans cela, un détournement effacerait sa propre trace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_bank_accounts', function (Blueprint $table) {
            $table->id();

            // CHIFFRÉS AU REPOS. Le cast `encrypted` du modèle s'en charge ; la colonne est un
            // texte parce qu'un chiffré est plus long que la donnée qu'il porte.
            $table->text('holder_name');
            $table->text('iban');
            $table->text('bic')->nullable();
            $table->text('bank_name')->nullable();

            // LES QUATRE DERNIERS, EN CLAIR ET SEULEMENT EUX. C'est ce qui s'affiche partout :
            // reconnaître son compte ne demande pas de le lire en entier.
            $table->string('iban_last4', 4);

            $table->string('country_code', 2)->default('BE');
            $table->string('currency', 3)->default('EUR');

            $table->text('note')->nullable();

            // UNE SEULE LIGNE ACTIVE À LA FOIS. L'index unique le grave : deux comptes actifs
            // voudraient dire deux destinations pour le même argent.
            $table->boolean('is_active')->default(true);
            $table->string('actif_unique', 1)
                ->virtualAs("case when is_active = 1 then '1' else null end")
                ->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_ip', 45)->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->unique('actif_unique', 'ux_un_seul_compte_bancaire_actif');
        });

        // ── LE CODE DU COFFRE, ET SES OUVERTURES ───────────────────────────
        Schema::create('platform_vault_accesses', function (Blueprint $table) {
            $table->id();

            // ouvert | refuse | modifie
            $table->string('action', 20);

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_ip', 45)->nullable();
            $table->string('actor_user_agent')->nullable();

            $table->foreignId('platform_bank_account_id')->nullable()
                ->constrained('platform_bank_accounts')->nullOnDelete();

            $table->timestamps();

            $table->index(['action', 'created_at']);
        });

        // LE CODE VIT SUR LE TITULAIRE DU SIÈGE, comme la phrase du siège — et il en est
        // DISTINCT : compromettre l'une n'ouvre pas l'autre.
        Schema::table('users', function (Blueprint $table) {
            $table->string('vault_code_hash')->nullable()->after('seat_claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('vault_code_hash');
        });

        Schema::dropIfExists('platform_vault_accesses');
        Schema::dropIfExists('platform_bank_accounts');
    }
};
