<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Central router: only loads domain-specific route files
*/

require __DIR__ . '/public.php';

Route::middleware(['auth', 'verified', 'active.account'])->group(function () {

    require __DIR__ . '/authenticated.php';
    require __DIR__ . '/integrations.php';

    require __DIR__ . '/admin.php';
    require __DIR__ . '/client.php';
    require __DIR__ . '/employe.php';

    require __DIR__ . '/feedback.php';
    require __DIR__ . '/missions.php';

    require __DIR__ . '/missing-route-fixes-advanced.php';
});
