<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Country;
use App\Livewire\Client\PrendreRendezVous;
use App\Livewire\Client\PremiumOfferPage;
use App\Http\Controllers\StripeWebhookController;

Route::view('/', 'home')->name('home');

Route::post('/locale', function (Request $request) {
    $locale = $request->string('locale')->toString();

    abort_unless(in_array($locale, ['fr', 'nl', 'en']), 404);

    session(['locale' => $locale]);

    if (auth()->check()) {
        $user = auth()->user();

        $user->update([
            'locale' => match ($locale) {
                'nl' => 'nl_BE',
                'en' => 'en_US',
                default => 'fr_BE',
            },
        ]);
    }

    return back();
})->name('locale.switch');

Route::post('/country', function (Request $request) {
    $country = Country::where('iso_code', strtoupper($request->country))
        ->where('is_active', true)
        ->firstOrFail();

    session(['country' => $country->iso_code]);

    return back();
})->name('country.switch');

Route::get('/premium', PremiumOfferPage::class)->name('premium.offer');
Route::get('/prendre-rendez-vous', PrendreRendezVous::class)->name('booking.create');

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);