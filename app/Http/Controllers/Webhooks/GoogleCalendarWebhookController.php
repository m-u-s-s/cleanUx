<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Calendar\GoogleCalendarBidirectionalService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * GCal bidirectionnel — endpoint des notifications push Google Calendar.
 * Google envoie un POST sans corps avec les en-têtes X-Goog-Channel-ID /
 * X-Goog-Resource-ID quand un calendrier surveillé change. On route vers le
 * service qui réimporte les créneaux occupés du prestataire concerné.
 */
class GoogleCalendarWebhookController extends Controller
{
    public function handle(Request $request, GoogleCalendarBidirectionalService $service): Response
    {
        $channelId = (string) $request->header('X-Goog-Channel-ID', '');
        $resourceId = (string) $request->header('X-Goog-Resource-ID', '');

        if ($channelId === '' || $resourceId === '') {
            return response('', 400);
        }

        try {
            $service->handlePushNotification($channelId, $resourceId);
        } catch (\Throwable $e) {
            Log::warning('[gcal_webhook] handling failed', ['error' => $e->getMessage()]);
        }

        // Google attend un 2xx ; sinon il retente puis suspend le canal.
        return response('', 200);
    }
}
