<?php

namespace App\Http\Controllers;

use App\Models\GoogleCalendarConnection;
use App\Services\Integrations\GoogleCalendarOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class GoogleCalendarAuthController extends Controller
{
    public function redirect(Request $request, GoogleCalendarOAuthService $oauth): RedirectResponse
    {
        abort_unless($request->user(), 403);

        $state = Str::uuid()->toString();

        Session::put('google_calendar.oauth_state', $state);

        return redirect()->away($oauth->buildAuthorizationUrl($state));
    }

    public function callback(Request $request, GoogleCalendarOAuthService $oauth): RedirectResponse
    {
        $expectedState = Session::pull('google_calendar.oauth_state');

        abort_unless($request->filled('code'), 403);
        abort_unless($request->filled('state') && hash_equals((string) $expectedState, (string) $request->string('state')), 403);

        $tokenPayload = $oauth->exchangeCode((string) $request->string('code'));
        $profile = $oauth->fetchUserProfile((string) $tokenPayload['access_token']);

        GoogleCalendarConnection::query()->updateOrCreate(
            ['user_id' => Auth::id()],
            $oauth->normalizeConnectionPayload($tokenPayload, $profile)
        );

        return redirect()->to($request->user()->isAdmin()
            ? route('admin.calendar.settings')
            : route('employe.google.calendar'))
            ->with('success', 'Compte Google Agenda connecté avec succès.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        GoogleCalendarConnection::query()->where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Connexion Google Agenda supprimée.');
    }
}
