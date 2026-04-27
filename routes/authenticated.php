<?php

use App\Models\User;
use App\Livewire\NotificationsCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function (Request $request) {
    $user = $request->user();

    if (! $user instanceof User) {
        abort(403);
    }

    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->isClient()) {
        return redirect()->route('client.dashboard');
    }

    if ($user->isEmploye()) {
        return redirect()->route('employe.dashboard');
    }

    abort(403);
})->name('dashboard');

Route::get('/notifications', NotificationsCenter::class)
    ->name('notifications.index');