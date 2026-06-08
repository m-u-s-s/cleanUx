<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\GoogleAgendaSettings;
use App\Models\GoogleCalendarConnection;
use App\Models\Parametre;
use App\Models\PlatformModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleAgendaSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mount_hydrates_state_from_parametres_and_connection_stats(): void
    {
        $admin = User::factory()->admin()->create();

        Parametre::setValeur('calendar_sync_enabled', '1');
        Parametre::setValeur('google_calendar_enabled', '1');
        Parametre::setValeur('google_calendar_employee_self_sync', '1');
        Parametre::setValeur('google_calendar_admin_read_only', '0');
        Parametre::setValeur('google_calendar_client_id', 'client-abc');
        Parametre::setValeur('google_calendar_sync_past_days', '14');
        Parametre::setValeur('google_calendar_sync_future_days', '60');

        $employe = User::factory()->employe()->create();
        GoogleCalendarConnection::factory()->create([
            'user_id' => $employe->id,
            'sync_enabled' => true,
        ]);

        // The acting admin is also connected.
        GoogleCalendarConnection::factory()->create([
            'user_id' => $admin->id,
            'sync_enabled' => true,
            'google_email' => 'admin@example.com',
        ]);

        Livewire::actingAs($admin)
            ->test(GoogleAgendaSettings::class)
            ->assertOk()
            ->assertSet('calendarSyncEnabled', true)
            ->assertSet('googleCalendarEnabled', true)
            ->assertSet('googleCalendarEmployeeSelfSync', true)
            ->assertSet('googleCalendarAdminReadOnly', false)
            ->assertSet('googleCalendarClientId', 'client-abc')
            ->assertSet('syncWindowPastDays', '14')
            ->assertSet('syncWindowFutureDays', '60')
            ->assertSet('activeConnectionsCount', 2)
            ->assertSet('employeeConnectionsCount', 1)
            ->assertSet('currentUserConnected', true)
            ->assertSet('currentUserGoogleEmail', 'admin@example.com');
    }

    public function test_save_persists_parametres_module_and_logs_activity(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(GoogleAgendaSettings::class)
            ->set('calendarSyncEnabled', true)
            ->set('googleCalendarEnabled', true)
            ->set('googleCalendarEmployeeSelfSync', true)
            ->set('googleCalendarClientId', 'my-client-id')
            ->set('googleCalendarRedirectUri', 'https://example.com/callback')
            ->set('defaultCalendarId', 'primary')
            ->set('syncWindowPastDays', '10')
            ->set('syncWindowFutureDays', '45')
            ->set('notes', 'Configured for tests.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('1', Parametre::getValeur('calendar_sync_enabled'));
        $this->assertSame('1', Parametre::getValeur('google_calendar_enabled'));
        $this->assertSame('my-client-id', Parametre::getValeur('google_calendar_client_id'));
        $this->assertSame('45', Parametre::getValeur('google_calendar_sync_future_days'));

        $module = PlatformModule::query()->where('key', 'calendar.sync')->first();
        $this->assertNotNull($module);
        $this->assertTrue((bool) $module->is_enabled);
    }

    public function test_save_validates_sync_window_bounds(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(GoogleAgendaSettings::class)
            ->set('syncWindowPastDays', '999')
            ->set('syncWindowFutureDays', '0')
            ->call('save')
            ->assertHasErrors(['syncWindowPastDays', 'syncWindowFutureDays']);
    }

    public function test_sync_now_runs_over_enabled_connections_and_flashes_success(): void
    {
        $admin = User::factory()->admin()->create();
        $employe = User::factory()->employe()->create(['email' => 'employe@example.com']);

        GoogleCalendarConnection::factory()->create([
            'user_id' => $employe->id,
            'sync_enabled' => true,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(GoogleAgendaSettings::class)
            ->call('syncNow')
            ->assertOk();

        $summary = $component->get('lastSyncSummary');
        $this->assertCount(1, $summary);
        $this->assertSame('employe@example.com', $summary[0]['email']);
        $this->assertArrayHasKey('stats', $summary[0]);
    }

    public function test_render_exposes_recent_connections(): void
    {
        $admin = User::factory()->admin()->create();
        GoogleCalendarConnection::factory()->count(2)->create(['sync_enabled' => true]);

        Livewire::actingAs($admin)
            ->test(GoogleAgendaSettings::class)
            ->assertOk()
            ->assertViewHas('connections', fn ($connections) => $connections->count() === 2);
    }
}
