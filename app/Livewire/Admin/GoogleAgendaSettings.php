<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\GoogleCalendarConnection;
use App\Models\Parametre;
use App\Models\PlatformModule;
use App\Services\Integrations\GoogleCalendarOAuthService;
use App\Services\Integrations\GoogleCalendarSyncService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GoogleAgendaSettings extends Component
{
    public bool $calendarSyncEnabled = false;
    public bool $googleCalendarEnabled = false;
    public bool $googleCalendarEmployeeSelfSync = false;
    public bool $googleCalendarAdminReadOnly = true;
    public string $googleCalendarClientId = '';
    public string $googleCalendarClientSecret = '';
    public string $googleCalendarRedirectUri = '';
    public string $googleCalendarScopes = 'openid email profile https://www.googleapis.com/auth/calendar';
    public string $defaultCalendarId = 'primary';
    public string $syncWindowPastDays = '7';
    public string $syncWindowFutureDays = '30';
    public string $notes = '';

    public int $activeConnectionsCount = 0;
    public int $employeeConnectionsCount = 0;
    public int $staleConnectionsCount = 0;
    public int $errorConnectionsCount = 0;
    public int $failedEventLinksCount = 0;
    public bool $currentUserConnected = false;
    public ?string $currentUserGoogleEmail = null;
    public array $lastSyncSummary = [];

    public function mount(GoogleCalendarOAuthService $oauth): void
    {
        $this->calendarSyncEnabled = Parametre::getValeur('calendar_sync_enabled', '0') === '1';
        $this->googleCalendarEnabled = Parametre::getValeur('google_calendar_enabled', '0') === '1';
        $this->googleCalendarEmployeeSelfSync = Parametre::getValeur('google_calendar_employee_self_sync', '0') === '1';
        $this->googleCalendarAdminReadOnly = Parametre::getValeur('google_calendar_admin_read_only', '1') === '1';
        $this->googleCalendarClientId = (string) Parametre::getValeur('google_calendar_client_id', '');
        $this->googleCalendarClientSecret = (string) Parametre::getValeur('google_calendar_client_secret', '');
        $this->googleCalendarRedirectUri = (string) Parametre::getValeur('google_calendar_redirect_uri', $oauth->redirectUri());
        $this->googleCalendarScopes = (string) Parametre::getValeur('google_calendar_scopes', 'openid email profile https://www.googleapis.com/auth/calendar');
        $this->defaultCalendarId = (string) Parametre::getValeur('google_calendar_default_calendar_id', 'primary');
        $this->syncWindowPastDays = (string) Parametre::getValeur('google_calendar_sync_past_days', '7');
        $this->syncWindowFutureDays = (string) Parametre::getValeur('google_calendar_sync_future_days', '30');
        $this->notes = (string) Parametre::getValeur('google_calendar_notes', '');

        $this->refreshConnectionStats();
    }

    public function save(): void
    {
        $this->validate([
            'googleCalendarClientId' => ['nullable', 'string', 'max:255'],
            'googleCalendarClientSecret' => ['nullable', 'string', 'max:255'],
            'googleCalendarRedirectUri' => ['nullable', 'url', 'max:255'],
            'googleCalendarScopes' => ['nullable', 'string', 'max:500'],
            'defaultCalendarId' => ['nullable', 'string', 'max:255'],
            'syncWindowPastDays' => ['required', 'integer', 'min:0', 'max:365'],
            'syncWindowFutureDays' => ['required', 'integer', 'min:1', 'max:730'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        Parametre::setValeur('calendar_sync_enabled', $this->calendarSyncEnabled ? '1' : '0');
        Parametre::setValeur('google_calendar_enabled', $this->googleCalendarEnabled ? '1' : '0');
        Parametre::setValeur('google_calendar_employee_self_sync', $this->googleCalendarEmployeeSelfSync ? '1' : '0');
        Parametre::setValeur('google_calendar_admin_read_only', $this->googleCalendarAdminReadOnly ? '1' : '0');
        Parametre::setValeur('google_calendar_client_id', $this->googleCalendarClientId);
        Parametre::setValeur('google_calendar_client_secret', $this->googleCalendarClientSecret);
        Parametre::setValeur('google_calendar_redirect_uri', $this->googleCalendarRedirectUri);
        Parametre::setValeur('google_calendar_scopes', $this->googleCalendarScopes);
        Parametre::setValeur('google_calendar_default_calendar_id', $this->defaultCalendarId);
        Parametre::setValeur('google_calendar_sync_past_days', (string) $this->syncWindowPastDays);
        Parametre::setValeur('google_calendar_sync_future_days', (string) $this->syncWindowFutureDays);
        Parametre::setValeur('google_calendar_notes', $this->notes);

        PlatformModule::query()->updateOrCreate(
            ['key' => 'calendar.sync'],
            [
                'name' => 'Synchronisation agenda',
                'description' => 'Connexion agenda interne / Google.',
                'category' => 'integrations',
                'sort_order' => 70,
                'is_enabled' => $this->calendarSyncEnabled,
                'settings' => [
                    'provider' => 'google',
                    'employee_self_sync' => $this->googleCalendarEmployeeSelfSync,
                    'admin_read_only' => $this->googleCalendarAdminReadOnly,
                    'default_calendar_id' => $this->defaultCalendarId,
                ],
            ]
        );

        if (class_exists(ActivityLog::class)) {
            ActivityLog::query()->create([
                'user_id' => Auth::id(),
                'action' => 'calendar.settings.updated',
                'target_type' => 'platform_module',
                'target_id' => null,
                'meta' => [
                    'calendar_sync_enabled' => $this->calendarSyncEnabled,
                    'google_calendar_enabled' => $this->googleCalendarEnabled,
                ],
            ]);
        }

        $this->refreshConnectionStats();
        session()->flash('success', 'Paramètres Google Agenda enregistrés.');
    }

    public function syncNow(GoogleCalendarSyncService $syncService): void
    {
        $results = [];

        foreach (GoogleCalendarConnection::query()->with('user')->where('sync_enabled', true)->get() as $connection) {
            $stats = $syncService->syncFutureRendezVousForUser(
                $connection->user,
                (int) $this->syncWindowFutureDays
            );

            $results[] = [
                'email' => $connection->user->email,
                'stats' => $stats,
            ];
        }

        $this->lastSyncSummary = $results;
        $this->refreshConnectionStats();

        session()->flash('success', 'Synchronisation Google Agenda exécutée.');
    }

    public function refreshConnectionStats(): void
    {
        $this->activeConnectionsCount = GoogleCalendarConnection::query()->where('sync_enabled', true)->count();
        $this->employeeConnectionsCount = GoogleCalendarConnection::query()
            ->where('sync_enabled', true)
            ->whereHas('user', fn ($q) => $q->where('role', 'employe'))
            ->count();

        $summary = app(GoogleCalendarSyncService::class)->healthSummary(24);
        $this->staleConnectionsCount = (int) ($summary['stale_connections'] ?? 0);
        $this->errorConnectionsCount = (int) ($summary['error_connections'] ?? 0);
        $this->failedEventLinksCount = (int) ($summary['failed_event_links'] ?? 0);

        $currentConnection = GoogleCalendarConnection::query()->where('user_id', Auth::id())->first();
        $this->currentUserConnected = (bool) $currentConnection;
        $this->currentUserGoogleEmail = $currentConnection?->google_email;
    }

    public function getConnectionsProperty()
    {
        return GoogleCalendarConnection::query()
            ->with('user')
            ->latest('updated_at')
            ->limit(20)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.google-agenda-settings', [
            'connections' => $this->connections,
        ])->layout('layouts.app');
    }
}
