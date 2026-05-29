<?php

namespace App\Livewire\ClientCompany;

use App\Services\Bookings\BulkBookingImporter as BulkImporterService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class BulkBookingImporter extends Component
{
    use WithFileUploads;

    public $csvFile;

    public string $separator = ',';

    public ?array $report = null;

    public string $error = '';

    public bool $importing = false;

    public function import(): void
    {
        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'separator' => ['required', 'in:,,;'],
        ]);

        $user = Auth::user();
        $orgId = (int) ($user->current_organization_id ?? $user->organization_account_id ?? 0);
        if ($orgId === 0) {
            $this->error = 'Aucune organisation sélectionnée. Vérifie ton profil ou contacte ton admin.';

            return;
        }

        $this->importing = true;
        $this->error = '';
        $this->report = null;

        try {
            $csvContent = file_get_contents($this->csvFile->getRealPath());
            $this->report = app(BulkImporterService::class)->import(
                $user,
                $orgId,
                $csvContent,
                $this->separator,
            );
        } catch (\Throwable $e) {
            $this->error = 'Erreur lors de l\'import : '.$e->getMessage();
        } finally {
            $this->importing = false;
        }
    }

    public function reset_(): void
    {
        $this->reset(['csvFile', 'report', 'error']);
    }

    public function render(): View
    {
        return view('livewire.client-company.bulk-booking-importer')->layout('layouts.app');
    }
}
