<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_export_routes(): void
    {
        $admin = User::factory()->admin()->create();
        Booking::factory()->count(2)->create();
        Feedback::factory()->count(2)->create();

        $this->actingAs($admin);

        $this->get(route('admin.export.pdf'))->assertOk();
        $this->get('/admin/export/pdf')->assertOk();
        $this->get(route('admin.feedbacks.export'))->assertOk();
        $this->get(route('admin.feedbacks.export.csv'))->assertOk();
    }

    public function test_non_admin_users_are_forbidden_from_export_routes(): void
    {
        $client = User::factory()->client()->create();
        $this->actingAs($client);

        $this->get(route('admin.export.pdf'))->assertForbidden();
        $this->get('/admin/export/pdf')->assertForbidden();
        $this->get(route('admin.feedbacks.export'))->assertForbidden();
        $this->get(route('admin.feedbacks.export.csv'))->assertForbidden();
    }
}
