<?php

namespace Tests\Feature\I18n;

use App\Models\User;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\I18n\Fixtures\TestLocalizedNotification;
use Tests\TestCase;

class NotificationLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_implements_has_locale_preference(): void
    {
        $user = User::factory()->create(['locale' => 'nl_BE']);
        $this->assertInstanceOf(
            HasLocalePreference::class,
            $user
        );
        $this->assertSame('nl', $user->preferredLocale());
    }

    public function test_preferred_locale_returns_null_when_empty_string(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locale' => ''])->save();
        $this->assertNull($user->fresh()->preferredLocale());
    }

    public function test_preferred_locale_returns_null_when_unsupported(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locale' => 'zz_ZZ'])->save();
        $this->assertNull($user->fresh()->preferredLocale());
    }

    public function test_app_locale_unchanged_after_send_to_other_locale_user(): void
    {
        App::setLocale('fr');

        $userNl = User::factory()->create(['locale' => 'nl_BE']);

        Notification::fake();

        $userNl->notify(new TestLocalizedNotification);

        Notification::assertSentTo($userNl, TestLocalizedNotification::class);

        $this->assertSame('fr', App::getLocale(), 'App locale should not be affected globally after notification');
    }
}
