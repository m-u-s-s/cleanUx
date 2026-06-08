<?php

namespace Tests\Feature\Admin\I18n;

use App\Livewire\Admin\I18n\TranslationsCenter;
use App\Models\TranslationOverride;
use App\Models\User;
use App\Services\I18n\LocaleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TranslationsCenterTest extends TestCase
{
    use RefreshDatabase;

    private function defaultLocale(): string
    {
        return app(LocaleResolver::class)->default();
    }

    public function test_mount_sets_locale_to_resolver_default_and_renders(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(TranslationsCenter::class)
            ->assertOk()
            ->assertSet('locale', $this->defaultLocale())
            ->assertSet('group', 'app')
            ->assertSee('Centre de traductions')
            ->assertSee('Compte'); // app.account value from lang/fr/app.php
    }

    public function test_start_and_cancel_edit_toggle_editing_state(): void
    {
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)
            ->test(TranslationsCenter::class)
            ->call('startEdit', 'app.account', 'Compte')
            ->assertSet('editingKey', 'app.account')
            ->assertSet('editingValue', 'Compte');

        $component->call('cancelEdit')
            ->assertSet('editingKey', null)
            ->assertSet('editingValue', '');
    }

    public function test_save_override_requires_a_value(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(TranslationsCenter::class)
            ->call('startEdit', 'app.account', 'Compte')
            ->set('editingValue', '')
            ->call('saveOverride')
            ->assertHasErrors(['editingValue' => 'required']);

        $this->assertDatabaseCount('translation_overrides', 0);
    }

    public function test_save_override_persists_record_and_dispatches_toast(): void
    {
        $admin = User::factory()->admin()->create();
        $locale = $this->defaultLocale();

        Livewire::actingAs($admin)
            ->test(TranslationsCenter::class)
            ->call('startEdit', 'app.account', 'Compte')
            ->set('editingValue', 'Mon Compte (édité)')
            ->call('saveOverride')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('editingKey', null)
            ->assertSet('editingValue', '');

        $this->assertDatabaseHas('translation_overrides', [
            'locale' => $locale,
            'group' => 'app',
            'key' => 'app.account',
            'namespace' => '*',
            'value' => 'Mon Compte (édité)',
            'is_published' => true,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_save_override_updates_existing_record(): void
    {
        $admin = User::factory()->admin()->create();
        $locale = $this->defaultLocale();

        TranslationOverride::create([
            'locale' => $locale,
            'group' => 'app',
            'key' => 'app.account',
            'value' => 'Ancienne valeur',
            'namespace' => '*',
            'is_published' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(TranslationsCenter::class)
            ->call('startEdit', 'app.account', 'Ancienne valeur')
            ->set('editingValue', 'Nouvelle valeur')
            ->call('saveOverride')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('translation_overrides', 1);
        $this->assertDatabaseHas('translation_overrides', [
            'key' => 'app.account',
            'value' => 'Nouvelle valeur',
        ]);
    }

    public function test_remove_override_deletes_the_record_and_dispatches_toast(): void
    {
        $admin = User::factory()->admin()->create();
        $locale = $this->defaultLocale();

        TranslationOverride::create([
            'locale' => $locale,
            'group' => 'app',
            'key' => 'account',
            'value' => 'Compte custom',
            'namespace' => '*',
            'is_published' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(TranslationsCenter::class)
            ->call('removeOverride', 'account')
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('translation_overrides', [
            'locale' => $locale,
            'group' => 'app',
            'key' => 'account',
        ]);
    }

    public function test_overrides_are_surfaced_and_filterable_by_show_only_overridden(): void
    {
        $admin = User::factory()->admin()->create();
        $locale = $this->defaultLocale();

        TranslationOverride::create([
            'locale' => $locale,
            'group' => 'app',
            'key' => 'account',
            'value' => 'Compte personnalisé DB',
            'namespace' => '*',
            'is_published' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(TranslationsCenter::class)
            // The overridden 'account' row is flagged with the DB badge and still shows its file value.
            ->assertSee('Compte')
            ->assertSee('Connexion') // app.login — a non-overridden sibling row
            ->set('showOnlyOverridden', true)
            ->assertOk()
            ->assertSee('Compte')
            ->assertDontSee('Connexion'); // filtered out, only overridden rows remain
    }

    public function test_search_filters_rows_to_a_no_match_empty_state(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(TranslationsCenter::class)
            ->set('search', 'zzz-no-such-key-exists-anywhere')
            ->assertOk()
            ->assertSee('Aucune clé ne correspond.');
    }

    public function test_search_matches_a_known_key(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(TranslationsCenter::class)
            ->set('search', 'account')
            ->assertOk()
            ->assertSee('Compte');
    }
}
