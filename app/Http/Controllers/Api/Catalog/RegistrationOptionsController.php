<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\RegistrationOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** LES MÉTIERS ET LES ZONES OFFERTS À L'INSCRIPTION — une seule API, web et mobile. */
class RegistrationOptionsController extends Controller
{
    public function __invoke(Request $request, RegistrationOptionsService $options): JsonResponse
    {
        $pays = $request->query('country');

        return response()->json([
            'ok' => true,
            'data' => $options->forCountry(is_string($pays) && $pays !== '' ? $pays : null),
        ]);
    }
}
