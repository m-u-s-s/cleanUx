<?php

namespace App\Services\KybV2\Providers;

use App\Services\KybV2\Contracts\SanctionsScreeningProviderContract;
use App\Services\KybV2\SanctionsResult;

/**
 * Mock sanctions screening — match si l'identifier ou name contient certains tokens.
 * Pour CI/dev. En prod, remplacer par ComplyAdvantage / Refinitiv / Dow Jones / Sayari.
 */
class MockSanctionsScreeningProvider implements SanctionsScreeningProviderContract
{
    /** Tokens qui déclenchent un faux match (case-insensitive substring match) */
    protected static array $sanctionedTokens = [
        'sanctioned', 'ofac', 'blacklist', 'kim jong', 'putin', 'wagner',
    ];
    protected static array $reviewTokens = [
        'review_required', 'partial_match',
    ];

    public function name(): string
    {
        return 'mock';
    }

    public function screen(string $nameOrIdentifier, string $listName, ?string $countryCode = null): SanctionsResult
    {
        $lower = mb_strtolower($nameOrIdentifier);

        foreach (self::$sanctionedTokens as $token) {
            if (str_contains($lower, $token)) {
                return new SanctionsResult(
                    hasMatch: true,
                    matchCount: 1,
                    listName: $listName,
                    matches: [
                        ['matched_token' => $token, 'confidence' => 0.95, 'list_entry' => 'MOCK-' . strtoupper($token)],
                    ],
                    provider: 'mock',
                );
            }
        }
        foreach (self::$reviewTokens as $token) {
            if (str_contains($lower, $token)) {
                return new SanctionsResult(
                    hasMatch: true,
                    matchCount: 1,
                    listName: $listName,
                    matches: [['matched_token' => $token, 'confidence' => 0.55]],
                    provider: 'mock',
                );
            }
        }
        return new SanctionsResult(
            hasMatch: false,
            matchCount: 0,
            listName: $listName,
            provider: 'mock',
        );
    }
}
