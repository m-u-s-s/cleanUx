<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** RATTRAPE LA SOCIÉTÉ EXÉCUTANTE SUR LES MISSIONS ET LES RENDEZ-VOUS DÉJÀ EN BASE. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missions') || ! Schema::hasColumn('missions', 'provider_organization_id')) {
            return;
        }

        $this->depuisLeRendezVous();
        $this->depuisLeProfilDuChefDeMission();
        $this->rendezVousDepuisLeProfilDeLEmploye();
    }

    public function down(): void
    {
        // Volontairement vide — voir l'en-tête.
    }

    /** Source la plus sûre : la société décidée sur le rendez-vous lui-même. */
    private function depuisLeRendezVous(): void
    {
        if (! Schema::hasColumn('bookings', 'assigned_provider_organization_id')) {
            return;
        }

        DB::table('missions')
            ->select('missions.id', 'bookings.assigned_provider_organization_id as org')
            ->join('bookings', 'bookings.id', '=', 'missions.rendez_vous_id')
            ->whereNull('missions.provider_organization_id')
            ->whereNotNull('bookings.assigned_provider_organization_id')
            ->orderBy('missions.id')
            ->chunkById(500, function ($lignes) {
                foreach ($lignes as $ligne) {
                    DB::table('missions')
                        ->where('id', $ligne->id)
                        ->whereNull('provider_organization_id')
                        ->update(['provider_organization_id' => $ligne->org]);
                }
            }, 'missions.id', 'id');
    }

    /** À défaut : la société du chef de mission, lue sur son PROFIL PRESTATAIRE. */
    private function depuisLeProfilDuChefDeMission(): void
    {
        $colonneChef = Schema::hasColumn('missions', 'lead_provider_user_id')
            ? 'lead_provider_user_id'
            : (Schema::hasColumn('missions', 'lead_employee_id') ? 'lead_employee_id' : null);

        if ($colonneChef === null || ! Schema::hasTable('provider_profiles')) {
            return;
        }

        DB::table('missions')
            ->select('missions.id', 'provider_profiles.organization_account_id as org')
            ->join('provider_profiles', 'provider_profiles.user_id', '=', 'missions.'.$colonneChef)
            ->whereNull('missions.provider_organization_id')
            ->whereNotNull('provider_profiles.organization_account_id')
            ->orderBy('missions.id')
            ->chunkById(500, function ($lignes) {
                foreach ($lignes as $ligne) {
                    DB::table('missions')
                        ->where('id', $ligne->id)
                        ->whereNull('provider_organization_id')
                        ->update(['provider_organization_id' => $ligne->org]);
                }
            }, 'missions.id', 'id');
    }

    /** Le rendez-vous lui-même, pour que le dispatch et la facturation lisent la même chose. */
    private function rendezVousDepuisLeProfilDeLEmploye(): void
    {
        if (! Schema::hasColumn('bookings', 'assigned_provider_organization_id')
            || ! Schema::hasColumn('bookings', 'employe_id')
            || ! Schema::hasTable('provider_profiles')) {
            return;
        }

        DB::table('bookings')
            ->select('bookings.id', 'provider_profiles.organization_account_id as org')
            ->join('provider_profiles', 'provider_profiles.user_id', '=', 'bookings.employe_id')
            ->whereNull('bookings.assigned_provider_organization_id')
            ->whereNotNull('provider_profiles.organization_account_id')
            ->orderBy('bookings.id')
            ->chunkById(500, function ($lignes) {
                foreach ($lignes as $ligne) {
                    // Query builder et NON `Booking::save()` : l'observateur `RendezVousObserver` écoute `Booking::saved` et relancerait la synchronisation de mission pour chaque ligne — au mieux un backfill interminable, au pire une boucle.
                    DB::table('bookings')
                        ->where('id', $ligne->id)
                        ->whereNull('assigned_provider_organization_id')
                        ->update(['assigned_provider_organization_id' => $ligne->org]);
                }
            }, 'bookings.id', 'id');
    }
};
