<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\RendezVous;
use App\Models\User;
use App\Models\Feedback;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class ExportTools extends Component
{
    public $type = 'rendez_vous';
    public $format = 'csv';

    public function export()
    {
        Gate::authorize('export', User::class);

        $data = match ($this->type) {
            'rendez_vous' => RendezVous::with(['client', 'employe'])->get(),
            'utilisateurs' => User::all(),
            'feedbacks' => Feedback::with(['client', 'rendezVous'])->get(),
            default => collect()
        };

        $filename = $this->type . '_' . now()->format('Ymd_His');

        if ($this->format === 'csv') {
            return $this->exportCsv($data, $filename);
        }

        if ($this->format === 'pdf') {
            return redirect()->route('admin.export.pdf', [
                'type' => $this->type
            ]);
        }
    }

    public function exportCsv($data, $filename)
    {
        Gate::authorize('export', User::class);

        if ($data->isEmpty()) {
            return session()->flash('error', 'Aucune donnée à exporter.');
        }

        $csv = '';
        $headers = array_keys($data->first()->getAttributes());
        $csv .= implode(',', $headers) . "\n";

        foreach ($data as $item) {
            $csv .= implode(',', array_map(
                fn($v) => '"' . Str::of($v)->replace('"', '""') . '"',
                $item->getAttributes()
            )) . "\n";
        }

        $path = "exports/{$filename}.csv";
        Storage::put($path, $csv);

        return Response::download(storage_path("app/{$path}"))->deleteFileAfterSend();
    }

    public function render()
    {
        return view('livewire.admin.export-tools');
    }
}