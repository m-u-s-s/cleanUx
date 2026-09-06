<?php

namespace App\Http\Controllers;

use App\Services\WebView\WebViewTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/** GET /m/enter?ticket=… Web (session) endpoint. */
class WebViewEntryController extends Controller
{
    public function __construct(private readonly WebViewTicketService $tickets) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $payload = $this->tickets->redeem((string) $request->query('ticket', ''));

        if ($payload === null) {
            return response()->view('webview.session-expired', [], 419);
        }

        if (Auth::loginUsingId($payload['user_id']) === false) {
            return response()->view('webview.session-expired', [], 419);
        }

        $target = $payload['target_path'];
        $separator = str_contains($target, '?') ? '&' : '?';

        return redirect()->to($target.$separator.'embed=1');
    }

    /**
     * LA PAGE QUE LE PONT ATTEND QUAND LA SESSION MEURT EN COURS DE ROUTE.
     *
     * Elle poste `sessionExpired` : l'application refait alors la passation avec son jeton,
     * sans que personne voie de formulaire de connexion.
     */
    public function expired(): Response
    {
        return response()->view('webview.session-expired', [], 419);
    }
}
