<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\I18n\LocaleResolver;
use Illuminate\Http\JsonResponse;

class LocaleListController extends Controller
{
    public function index(LocaleResolver $resolver): JsonResponse
    {
        return response()->json([
            'default' => $resolver->default(),
            'fallback' => $resolver->fallback(),
            'current' => app()->getLocale(),
            'locales' => $resolver->availableForSwitcher(),
        ]);
    }
}
