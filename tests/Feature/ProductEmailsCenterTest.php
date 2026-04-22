<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProductEmailsCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductEmailsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_product_emails_center_and_generate_preview(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.emails'))
            ->assertOk()
            ->assertSee('Emails produit')
            ->assertSee('Aperçu email');

        Livewire::actingAs($admin)
            ->test(ProductEmailsCenter::class)
            ->set('templateKey', 'finance_reminder')
            ->call('generatePreview')
            ->assertSee('Un solde reste à régler');

        $this->assertDatabaseHas('email_logs', [
            'status' => 'preview',
            'template_key' => 'finance_reminder',
            'previewed_by_user_id' => $admin->id,
        ]);
    }
}
