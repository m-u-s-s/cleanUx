<?php

namespace App\Providers;

use App\Models\Feedback;
use App\Models\FinanceInvoice;
use App\Models\Mission;
use App\Models\Booking;
use App\Models\User;
use App\Policies\FeedbackPolicy;
use App\Policies\FinanceInvoicePolicy;
use App\Policies\MissionPolicy;
use App\Policies\RendezVousPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Booking::class => RendezVousPolicy::class,
        Feedback::class => FeedbackPolicy::class,
        User::class => UserPolicy::class,
        Mission::class => MissionPolicy::class,
        FinanceInvoice::class => FinanceInvoicePolicy::class,
    ];

    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user) => $user->isAdmin() && $user->is_active);
        Gate::define('access-client', fn (User $user) => $user->isClient() && $user->is_active);
        Gate::define('access-employe', fn (User $user) => $user->isEmploye() && $user->is_active);

        Gate::define('manage-calendar', fn (User $user) => $user->canAccessAdminModule('manage-calendar'));
        Gate::define('manage-users', fn (User $user) => $user->canAccessAdminModule('manage-users'));
        Gate::define('manage-services', fn (User $user) => $user->canAccessAdminModule('manage-services'));
        Gate::define('manage-entreprises', fn (User $user) => $user->canAccessAdminModule('manage-entreprises'));
        Gate::define('manage-finance', fn (User $user) => $user->canAccessAdminModule('manage-finance'));
        Gate::define('manage-analytics', fn (User $user) => $user->canAccessAdminModule('manage-analytics'));
        Gate::define('manage-quality', fn (User $user) => $user->canAccessAdminModule('manage-quality'));
        Gate::define('manage-premium', fn (User $user) => $user->canAccessAdminModule('manage-premium'));
        Gate::define('manage-audit-logs', fn (User $user) => $user->canAccessAdminModule('manage-audit-logs'));
        Gate::define('manage-modules', fn (User $user) => $user->canAccessAdminModule('manage-modules'));
        

        Gate::define('access-team-lead-ops', fn (User $user) => $user->isEmploye() && $user->is_active && $user->isFieldTeamLead());

        Gate::define('perform-critical-admin-actions', function (User $user) {
            if (! $user->canAccessAdminModule('perform-critical-admin-actions')) {
                return false;
            }

            return ! $user->isReadOnlyAdmin();
        });
    }
}
