<?php

use App\Http\Controllers\StripeWebhookController;
use App\Livewire\Client\PremiumOfferPage;
use App\Livewire\Client\PrendreRendezVous;
use App\Models\Country;
use App\Models\User;
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

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');