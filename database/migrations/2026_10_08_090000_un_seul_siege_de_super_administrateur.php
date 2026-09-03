<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UN SEUL SIÈGE DE SUPER-ADMINISTRATEUR, ET IL VIT DANS LES DONNÉES.
 *
 * Aucune adresse en dur, aucune variable d'environnement : le siège est une LIGNE, désignée à
 * l'exécution par `php artisan plateforme:siege`. Ce qui est gravé ici, c'est l'invariant — au
 * plus un `platform_role = 'super_admin'` dans toute la plateforme — pas son titulaire.
 *
 * LE VERROU EST UNE COLONNE GÉNÉRÉE, pas un contrôle applicatif. Un crochet de modèle ne voit ni
 * `DB::table()->update()`, ni un import SQL, ni une console de base de données. L'index unique,
 * lui, les voit tous : un second super-administrateur devient physiquement impossible à écrire.
 * (Les valeurs nulles échappent à l'unicité, en MySQL comme en SQLite : les autres comptes ne se
 * gênent donc pas entre eux.)
 *
 * LE SIÈGE PART VACANT. Rétrograder d'office le compte au plus petit `id` ferait hériter le
 * passe-partout de quelqu'un que personne n'a désigné. Tous les titulaires actuels passent
 * `admin` — ils gardent leurs permissions, ils perdent le passe-partout — et le siège attend
 * qu'on le réclame.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Le siège se vide, personne n'en hérite par accident ──────────
        $anciens = DB::table('users')->where('platform_role', 'super_admin')->pluck('email')->all();

        DB::table('users')->where('platform_role', 'super_admin')->update(['platform_role' => 'admin']);

        // LA SECONDE NOTION S'ÉTEINT AUSSI. `is_super_admin` ouvrait `hasAdminPermission()` à lui
        // seul, AVANT toute vérification de rôle : la laisser vraie quelque part garderait un
        // second super-administrateur de fait, invisible au compte que ce verrou protège.
        if (Schema::hasColumn('users', 'is_super_admin')) {
            DB::table('users')->where('is_super_admin', true)->update(['is_super_admin' => false]);
        }

        if ($anciens !== []) {
            logger()->warning('[siege] Sièges de super-administrateur libérés par la migration.', [
                'comptes' => $anciens,
                'suite' => 'php artisan plateforme:siege <email>',
            ]);
        }

        // ── 2. La phrase du siège, propre à son titulaire ───────────────────
        Schema::table('users', function (Blueprint $table) {
            // ELLE N'EST PAS LE MOT DE PASSE DE CONNEXION : une session volée ou un mot de passe
            // deviné ne suffisent pas à déplacer le siège. Elle meurt avec le titulaire.
            $table->string('seat_secret_hash')->nullable()->after('platform_role');
            $table->timestamp('seat_claimed_at')->nullable()->after('seat_secret_hash');
        });

        // ── 3. L'invariant, gravé ───────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->string('super_admin_seat', 1)
                ->virtualAs("case when platform_role = 'super_admin' then '1' else null end")
                ->nullable();

            $table->unique('super_admin_seat', 'ux_users_un_seul_super_admin');
        });

        // ── 4. Les transferts, armés puis effectifs ─────────────────────────
        Schema::create('platform_seat_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();

            // UN TRANSFERT NE S'APPLIQUE PAS SUR-LE-CHAMP. Le titulaire est prévenu à l'armement
            // et garde le délai pour l'annuler : un voleur de session ne peut ni faire vite,
            // ni faire en silence.
            $table->timestamp('armed_at');
            $table->timestamp('effective_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_reason')->nullable();

            $table->string('armed_ip', 45)->nullable();
            $table->string('armed_user_agent')->nullable();

            $table->timestamps();

            $table->index(['to_user_id', 'confirmed_at']);
            $table->index(['effective_at', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_seat_transfers');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('ux_users_un_seul_super_admin');
            $table->dropColumn('super_admin_seat');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['seat_secret_hash', 'seat_claimed_at']);
        });
    }
};
