<?php

use App\Models\User;
use App\Http\Controllers\Admin\MissionAdminController;
use App\Http\Controllers\Admin\MissionQualityExportController;
use App\Http\Controllers\Client\FinanceDocumentDownloadController;
use App\Http\Controllers\ExportRendezVousController;
use App\Http\Controllers\FeedbackExportController;
use App\Http\Controllers\FeedbackInviteController;
use App\Http\Controllers\GoogleCalendarAuthController;
use App\Http\Controllers\PremiumCheckoutController;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\Admin\AdminPremiumClients;
use App\Livewire\Admin\AnalyticsCenter;
use App\Livewire\Admin\AutomationMissionGenerationCenter;
use App\Livewire\Employe\TeamLeadOperationsCenter;
use App\Livewire\Employe\EquipeTerrain;
use App\Livewire\Employe\CoordinationChantier;
use App\Livewire\Admin\OrchestrationTerrainCenter;
use App\Livewire\Admin\InternationalOperationsCenter;
use App\Livewire\Admin\GestionEquipesPartenaires;
use App\Livewire\Admin\B2BOperationsCenter;
use App\Livewire\Admin\AuditLogsCenter;
use App\Livewire\Admin\CalendrierInterne;
use App\Livewire\Admin\CatalogueServices;
use App\Livewire\Admin\EditRecurringBooking as AdminEditRecurringBooking;
use App\Livewire\Admin\FinanceCenter;
use App\Livewire\Admin\GestionEntreprises;
use App\Livewire\Admin\GestionZones;
use App\Livewire\Admin\GoogleAgendaSettings;
use App\Livewire\Admin\MissionsAdmin;
use App\Livewire\Admin\OutilsAdmin;
use App\Livewire\Admin\PlanningAdmin;
use App\Livewire\Admin\PlatformModulesCenter;
use App\Livewire\Admin\ProductEmailsCenter;
use App\Livewire\Admin\UtilisateursAdmin;
use App\Livewire\AdminDashboard;
use App\Livewire\AdminFeedbacks;
use App\Livewire\Client\EditRecurringBooking as ClientEditRecurringBooking;
use App\Livewire\Client\FavoriteEmployesManager;
use App\Livewire\Client\FinanceDocumentsClient;
use App\Livewire\Client\HistoriqueClient;
use App\Livewire\Client\LitigesClient;
use App\Livewire\Client\MesRendezVousClient;
use App\Livewire\Client\PremiumOfferPage;
use App\Livewire\Client\PrendreRendezVous;
use App\Livewire\Client\ProfilClient;
use App\Livewire\ClientDashboard;
use App\Livewire\Employe\DisponibilitesEmploye;
use App\Livewire\Employe\GoogleAgendaEmploye;
use App\Livewire\Employe\HistoriqueEmploye;
use App\Livewire\Employe\MissionsEmploye;
use App\Livewire\Employe\PlanningEmploye;
use App\Livewire\Employe\SignalerIncident;
use App\Livewire\EmployeDashboard;
use App\Livewire\NotificationsCenter;
use App\Models\Country;
use App\Models\Feedback;
use App\Models\RendezVous;
use App\Http\Controllers\MissionTrackingController;
use App\Http\Controllers\MissionFieldActionController;
use App\Http\Controllers\MissionReportController;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::post('/locale', function (Request $request) {
    $locale = $request->string('locale')->toString();
    abort_unless(in_array($locale, ['fr', 'nl', 'en'], true), 404);

    session(['locale' => $locale]);

    if (auth()->check()) {
        /** @var User $user */
        $user = auth()->user();

        $user->forceFill([
            'locale' => match ($locale) {
                'nl' => 'nl_BE',
                'en' => 'en_US',
                default => 'fr_BE',
            },
        ])->save();
    }

    return back();
})->name('locale.switch');

Route::post('/country', function (Request $request) {
    $country = Country::query()
        ->where('iso_code', strtoupper($request->string('country')->toString()))
        ->where('is_active', true)
        ->firstOrFail();

    $request->session()->put('country', $country->iso_code);

    if (auth()->check()) {
        /** @var User $user */
        $user = auth()->user();

        $metadata = (array) ($user->metadata ?? []);
        $metadata['current_country_code'] = $country->iso_code;

        $user->forceFill(['metadata' => $metadata])->save();
    }

    return back();
})->name('country.switch');

Route::get('/premium', PremiumOfferPage::class)->name('premium.offer');
Route::get('/prendre-rendez-vous', PrendreRendezVous::class)->name('booking.create');
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('cashier.webhook');

Route::middleware(['auth', 'verified', 'active.account'])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isClient()) {
            return redirect()->route('client.dashboard');
        }

        if ($user->isEmploye()) {
            return redirect()->route('employe.dashboard');
        }

        abort(403);
    })->name('dashboard');

    Route::get('/notifications', NotificationsCenter::class)->name('notifications.index');

    Route::get('/integrations/google-agenda/connect', [GoogleCalendarAuthController::class, 'redirect'])
        ->name('google.calendar.connect');
    Route::get('/integrations/google-agenda/callback', [GoogleCalendarAuthController::class, 'callback'])
        ->name('google.calendar.callback');
    Route::post('/integrations/google-agenda/disconnect', [GoogleCalendarAuthController::class, 'disconnect'])
        ->name('google.calendar.disconnect');
    Route::post('/missions/{mission}/arrived', [MissionFieldActionController::class, 'arrived'])
        ->name('missions.arrived');


    Route::middleware('auth')->get('/missions/{mission}/report/pdf', [MissionReportController::class, 'download'])
        ->name('missions.report.pdf');
    Route::post('/premium/checkout', [PremiumCheckoutController::class, 'checkout'])->name('premium.checkout');
    Route::get('/premium/success', [PremiumCheckoutController::class, 'success'])->name('premium.success');
    Route::get('/premium/cancel', [PremiumCheckoutController::class, 'cancel'])->name('premium.cancel');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', AdminDashboard::class)->name('dashboard');
        Route::get('calendrier', CalendrierInterne::class)->middleware('can:manage-calendar')->name('calendar');
        Route::get('integrations/google-agenda', GoogleAgendaSettings::class)->middleware('can:manage-calendar')->name('calendar.settings');
        Route::get('planning', PlanningAdmin::class)->name('planning');
        Route::get('missions', MissionsAdmin::class)->name('missions');
        Route::get('utilisateurs', UtilisateursAdmin::class)->middleware('can:manage-users')->name('utilisateurs');
        Route::view('utilisateurs/gestion-avancee', 'admin.gestion-utilisateurs-page')->middleware('can:manage-users')->name('utilisateurs.manage');
        Route::get('feedbacks', AdminFeedbacks::class)->name('feedbacks');
        Route::get('zones', GestionZones::class)->middleware('can:manage-services')->name('zones');
        Route::get('outils', OutilsAdmin::class)->name('outils');
        Route::get('premium-clients', AdminPremiumClients::class)->middleware('can:manage-premium')->name('premium.clients');
        Route::get('services', CatalogueServices::class)->middleware('can:manage-services')->name('services');
        Route::get('entreprises', GestionEntreprises::class)->middleware('can:manage-entreprises')->name('entreprises');
        Route::get('finance', FinanceCenter::class)->middleware('can:manage-finance')->name('finance');
        Route::get('analytics', AnalyticsCenter::class)->middleware('can:manage-analytics')->name('analytics');
        Route::get('automatisation', AutomationMissionGenerationCenter::class)->middleware('can:manage-entreprises')->name('automation');
        Route::get('equipes-partenaires', GestionEquipesPartenaires::class)->middleware('can:manage-entreprises')->name('teams.partners');
        Route::get('b2b/operations', B2BOperationsCenter::class)->middleware('can:manage-entreprises')->name('b2b.operations');
        Route::get('international', InternationalOperationsCenter::class)->middleware('can:manage-services')->name('international');
        Route::get('orchestration', OrchestrationTerrainCenter::class)->middleware('can:manage-entreprises')->name('orchestration');
        Route::get('qualite', App\Livewire\Admin\IncidentsQualiteCenter::class)->middleware('can:manage-quality')->name('quality');
        Route::get('audit-logs', AuditLogsCenter::class)->middleware('can:manage-audit-logs')->name('audit.logs');
        Route::get('emails', ProductEmailsCenter::class)->name('emails');
        Route::get('modules', PlatformModulesCenter::class)->middleware('can:manage-modules')->name('modules');
        Route::view('pays', 'admin.countries-page')
            ->middleware('can:manage-services')
            ->name('countries');
        Route::get('rendez-vous/serie/{rendezVous}', AdminEditRecurringBooking::class)->name('rendezvous.series.edit');

        Route::get('/quality/export/incidents.csv', [MissionQualityExportController::class, 'incidentsCsv'])
            ->name('quality.export.incidents.csv');

        Route::get('/quality/export/missions.csv', [MissionQualityExportController::class, 'missionsCsv'])
            ->name('quality.export.missions.csv');

        Route::get('/missions/{mission}/export-pdf', [MissionReportController::class, 'download'])
            ->name('missions.export.pdf');
        Route::get('feedbacks/export', [FeedbackExportController::class, 'export'])->name('feedbacks.export');
        Route::get('feedbacks/export-csv', [FeedbackExportController::class, 'exportCsv'])->name('feedbacks.export.csv');
        Route::get('export/pdf', function () {
            $type = request('type');
            $view = match ($type) {
                'utilisateurs' => 'exports.users',
                'feedbacks' => 'exports.feedbacks',
                default => 'exports.rendezvous',
            };
            $data = match ($type) {
                'utilisateurs' => User::all(),
                'feedbacks' => Feedback::with(['client', 'rendezVous'])->get(),
                default => RendezVous::with(['client', 'employe'])->get(),
            };

            return Pdf::loadView($view, ['data' => $data])->download($type . '_' . now()->format('Ymd_His') . '.pdf');
        })->name('export.pdf');
        Route::get('export/{format}/{employeId?}', [ExportRendezVousController::class, 'export'])->name('export.rendezvous');
    });

    Route::middleware('role:client')->prefix('dashboard/client')->name('client.')->group(function () {
        Route::get('/', ClientDashboard::class)->name('dashboard');
        Route::get('rendez-vous/nouveau', PrendreRendezVous::class)->name('rendezvous.create');
        Route::get('rendez-vous', MesRendezVousClient::class)->name('rendezvous.index');
        Route::get('rendez-vous/serie/{rendezVous}', ClientEditRecurringBooking::class)->name('rendezvous.series.edit');
        Route::get('historique', HistoriqueClient::class)->name('historique');
        Route::get('litiges', LitigesClient::class)->name('claims');
        Route::get('profil', ProfilClient::class)->name('profile');
        Route::get('favoris-employes', FavoriteEmployesManager::class)->name('favorite-employes');
        Route::get('finance', FinanceDocumentsClient::class)->name('finance');
        Route::get('finance/devis/{quote}', [FinanceDocumentDownloadController::class, 'quote'])->name('finance.quote.download');
        Route::get('finance/factures/{invoice}', [FinanceDocumentDownloadController::class, 'invoice'])->name('finance.invoice.download');
    });

    Route::middleware('role:employe')->prefix('dashboard/employe')->name('employe.')->group(function () {
        Route::get('/', EmployeDashboard::class)->name('dashboard');
        Route::get('planning', PlanningEmploye::class)->name('planning');
        Route::get('missions', MissionsEmploye::class)->name('missions');
        Route::view('feedbacks', 'employe.feedbacks-page')->name('feedbacks');
        Route::view('validation-multiple', 'employe.validation-multiple-page')->name('validation.multiple');
        Route::get('google-agenda', GoogleAgendaEmploye::class)->name('google.calendar');
        Route::get('disponibilites', DisponibilitesEmploye::class)->name('disponibilites');
        Route::get('incident', SignalerIncident::class)->name('incident');
        Route::get('historique', HistoriqueEmploye::class)->name('historique');
        Route::get('equipe', EquipeTerrain::class)->name('team');
        Route::get('coordination', CoordinationChantier::class)->name('coordination');
        Route::get('chef-equipe', TeamLeadOperationsCenter::class)->middleware('can:access-team-lead-ops')->name('teamlead.operations');
    });

    Route::middleware('role:client')->prefix('feedback')->name('feedback.')->group(function () {
        Route::get('ajouter/{rendezVous}', [FeedbackInviteController::class, 'create'])->name('create');
        Route::post('ajouter/{rendezVous}', [FeedbackInviteController::class, 'store'])->name('store');
    });

    // mission tracking



    Route::middleware(['auth'])->group(function () {
        Route::post('/missions/{mission}/tracking/start', [MissionTrackingController::class, 'start'])
            ->name('missions.tracking.start');

        Route::post('/mission-tracking-sessions/{session}/tracking/push', [MissionTrackingController::class, 'push'])
            ->name('missions.tracking.push');

        Route::post('/mission-tracking-sessions/{session}/tracking/stop', [MissionTrackingController::class, 'stop'])
            ->name('missions.tracking.stop');

        Route::get('/missions/{mission}/tracking/live', [MissionTrackingController::class, 'live'])
            ->name('missions.tracking.live');
    });


    Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/missions/{mission}', [MissionAdminController::class, 'show'])->name('missions.show');
    });
});
