<?php

namespace App\Http\Controllers\Api\Admin\Console;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportRegistry;
use App\Admin\Console\ReportTile;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Sert un rapport d'administration : des sections, des tuiles chiffrées. */
class ReportController extends Controller
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function __invoke(string $report): JsonResponse
    {
        $rapport = $this->registry->for($report);

        if (! $rapport instanceof AdminReport) {
            return response()->json([
                'ok' => false,
                'error' => 'unknown_report',
                'error_code' => 'unknown_report',
            ], 404);
        }

        $sections = [];

        foreach ($rapport->sections() as $section) {
            $sections[] = [
                'title' => $section['title'],
                // Chaque tuile se mesure ici, et attrape ses propres erreurs : une table absente
                // coûte une tuile, jamais l'écran entier.
                'tiles' => array_map(fn (ReportTile $tile) => $tile->toArray(), $section['tiles']),
            ];
        }

        return response()->json(['ok' => true, 'key' => $rapport->key(), 'sections' => $sections]);
    }
}
