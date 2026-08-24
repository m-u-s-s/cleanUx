<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A3 — Migre les données vers les champs typés. NE SUPPRIME PAS la colonne, malgré son nom. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        // 1. platform_role migration (column IS on users table)
        DB::table('users')
            ->where('role', 'admin')
            ->where(function ($q) {
                $q->whereNull('platform_role')->orWhere('platform_role', 'user');
            })
            ->update(['platform_role' => 'admin']);

        // 2. provider_type migration — update provider_profiles for employe users
        //    who don't yet have a provider_profiles row with a provider_type.
        if (Schema::hasTable('provider_profiles')) {
            $employeUserIds = DB::table('users')
                ->where('role', 'employe')
                ->pluck('id');

            foreach ($employeUserIds as $userId) {
                $exists = DB::table('provider_profiles')
                    ->where('user_id', $userId)
                    ->exists();

                if ($exists) {
                    DB::table('provider_profiles')
                        ->where('user_id', $userId)
                        ->whereNull('provider_type')
                        ->update(['provider_type' => 'independent']);
                } else {
                    DB::table('provider_profiles')->insert([
                        'user_id' => $userId,
                        'provider_type' => 'independent',
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 3. customer_type migration — update customer_profiles for client/entreprise users.
        if (Schema::hasTable('customer_profiles')) {
            $clientUsers = DB::table('users')
                ->whereIn('role', ['client', 'entreprise'])
                ->select('id', 'role')
                ->get();

            foreach ($clientUsers as $user) {
                $customerType = $user->role === 'entreprise' ? 'company' : 'personal';

                $exists = DB::table('customer_profiles')
                    ->where('user_id', $user->id)
                    ->exists();

                if ($exists) {
                    DB::table('customer_profiles')
                        ->where('user_id', $user->id)
                        ->whereNull('customer_type')
                        ->update(['customer_type' => $customerType]);
                } else {
                    DB::table('customer_profiles')->insert([
                        'user_id' => $user->id,
                        'customer_type' => $customerType,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 4. Mark role as deprecated — keep column for backward compat with tests.
        //    Will be dropped in a future migration once all tests are updated.
        // Schema::table('users', function (Blueprint $table) {
        //     $table->dropColumn('role');
        // });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client')->after('account_type');
        });

        // Reverse data migration: read from profile tables back to role.
        if (Schema::hasTable('provider_profiles')) {
            $providerUserIds = DB::table('provider_profiles')->pluck('user_id');

            DB::table('users')
                ->whereIn('id', $providerUserIds)
                ->update(['role' => 'employe']);
        }

        if (Schema::hasTable('customer_profiles')) {
            $companyCustomerIds = DB::table('customer_profiles')
                ->where('customer_type', 'company')
                ->pluck('user_id');

            $personalCustomerIds = DB::table('customer_profiles')
                ->where('customer_type', 'personal')
                ->pluck('user_id');

            DB::table('users')
                ->whereIn('id', $companyCustomerIds)
                ->update(['role' => 'entreprise']);

            DB::table('users')
                ->whereIn('id', $personalCustomerIds)
                ->update(['role' => 'client']);
        }

        DB::table('users')
            ->whereIn('platform_role', ['admin', 'super_admin'])
            ->update(['role' => 'admin']);
    }
};
