<?php

namespace App\Services\Calendar\Contracts;

use App\Models\GoogleCalendarConnection;
use Carbon\CarbonInterface;

/**
 * Abstraction de l'accès Google Calendar pour le sens entrant — permet de tester
 * la boucle de synchro sans dépendre de l'API Google réelle (impl Mock vs Google).
 */
interface GoogleBusyFetcher
{
    /**
     * Récupère les événements « busy » du calendrier sur une fenêtre.
     *
     * @return array<int, array{external_id: string, start: string, end: string, all_day: bool, summary?: string}>
     */
    public function fetchBusyEvents(GoogleCalendarConnection $connection, CarbonInterface $from, CarbonInterface $to): array;

    /**
     * Enregistre un canal de notification push sur le calendrier.
     *
     * @return array{channel_id: string, resource_id: string, expiry: string}
     */
    public function registerWatch(GoogleCalendarConnection $connection, string $callbackUrl): array;
}
