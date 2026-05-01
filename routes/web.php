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

    Route::middleware(['auth', 'verified', 'active.account', 'role:admin'])
        ->get('/admin/utilisateurs/manage', function () {
            if (class_exists(\App\Livewire\Admin\UtilisateursAdmin::class)) {
                return \Livewire\Livewire::mount('admin.utilisateurs-admin');
            }

            return response('<h1>Gestion utilisateurs</h1>', 200);
        })
        ->name('admin.utilisateurs.manage');
});
