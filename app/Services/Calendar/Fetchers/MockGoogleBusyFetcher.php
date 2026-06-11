<?php

namespace App\Services\Calendar\Fetchers;

use App\Models\GoogleCalendarConnection;
use App\Services\Calendar\Contracts\GoogleBusyFetcher;
use Carbon\CarbonInterface;

/**
 * Fetcher de test/dev — renvoie des événements en mémoire (aucun appel réseau).
 * Utilisé par défaut tant que l'API Google n'est pas configurée.
 */
class MockGoogleBusyFetcher implements GoogleBusyFetcher
{
    /** @var array<int, array<string, mixed>> */
    public array $events = [];

    public string $channelId = 'mock-channel';

    public string $resourceId = 'mock-resource';

    public function fetchBusyEvents(GoogleCalendarConnection $connection, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->events;
    }

    public function registerWatch(GoogleCalendarConnection $connection, string $callbackUrl): array
    {
        return [
            'channel_id' => $this->channelId,
            'resource_id' => $this->resourceId,
            'expiry' => (string) now()->addDays(7)->toIso8601String(),
        ];
    }
}
