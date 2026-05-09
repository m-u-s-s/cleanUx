<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\Feedback;
use App\Models\Booking;
use App\Models\User;
use Livewire\Component;

class OutilsDeTest extends Component
{
    public function getStatsProperty(): array
    {
        return [
            'utilisateurs' => User::count(),
            'clients' => User::clientFacing()->count(),
            'employes' => User::where('role', 'employe')->count(),
            'rendez_vous' => Booking::count(),
            'feedbacks' => Feedback::count(),
            'logs' => ActivityLog::count(),
        ];
    }

    public function getSeedCommandsProperty(): array
    {
        return [
            'php artisan migrate:fresh --seed',
            'php artisan app:seed-platform demo --fresh',
            'php artisan app:seed-platform reference --fresh',
            'php artisan app:seed-platform production --fresh --force',
            'php artisan db:seed --class=DatabaseSeeder',
        ];
    }

    public function getUsefulCommandsProperty(): array
    {
        return [
            'php artisan test',
            'php artisan app:prepare-fresh-seed --strict',
            'php artisan app:audit-platform-integrity --fail-on-issues',
            'php artisan route:list',
            'php artisan optimize:clear',
            'php artisan config:clear',
        ];
    }

    public function render()
    {
        return view('livewire.admin.outils-de-test', [
            'stats' => $this->stats,
            'seedCommands' => $this->seedCommands,
            'usefulCommands' => $this->usefulCommands,
        ]);
    }
}
