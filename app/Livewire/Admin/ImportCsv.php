<?php

namespace App\Livewire\Admin;

use App\Models\OrganizationAccount;
use App\Models\RendezVous;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportCsv extends Component
{
    use WithFileUploads;

    public $csv;
    public string $type = 'clients';

    public function import(): void
    {
        Gate::authorize('import', User::class);

        $this->validate([
            'csv' => 'required|file|mimes:csv,txt',
        ]);

        ActivityLogger::log('import_csv_execute', null, [
            'type' => $this->type ?? 'inconnu',
            'filename' => $this->csv?->getClientOriginalName(),
        ]);

        $path = $this->csv->store('imports');
        $rows = array_map('str_getcsv', file(storage_path('app/'.$path)));

        if (empty($rows)) {
            session()->flash('error', 'Le fichier CSV est vide.');
            return;
        }

        $headers = array_map(fn ($header) => strtolower(trim($header)), array_shift($rows));
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;

            if (count($row) !== count($headers)) {
                $skipped++;
                $errors[] = "Ligne {$lineNumber} : nombre de colonnes invalide.";
                continue;
            }

            $data = array_combine($headers, $row);

            if ($this->type === 'clients') {
                $validator = Validator::make($data, [
                    'name' => 'required',
                    'email' => 'required|email|unique:users,email',
                    'password' => 'required',
                    'role' => 'nullable|in:client,entreprise',
                ]);

                if ($validator->fails()) {
                    $skipped++;
                    $errors[] = "Ligne {$lineNumber} : client invalide.";
                    continue;
                }

                $role = in_array(($data['role'] ?? 'client'), [ 'entreprise'], true) ? 'entreprise' : ($data['role'] ?: 'client');
                $organizationAccountId = null;

                if ($role === 'entreprise') {
                    $orgName = trim($data['organization_name'] ?? $data['company_name'] ?? $data['name']);
                    $slugBase = Str::slug($orgName) ?: 'entreprise';
                    $slug = $slugBase;
                    $i = 1;
                    while (OrganizationAccount::where('slug', $slug)->exists()) {
                        $slug = $slugBase.'-'.$i++;
                    }

                    $organization = OrganizationAccount::create([
                        'name' => $orgName,
                        'legal_name' => $orgName,
                        'slug' => $slug,
                        'type' => 'entreprise',
                        'tva_number' => $data['tva_number'] ?? null,
                        'email' => trim($data['email']),
                        'status' => 'active',
                    ]);

                    $organizationAccountId = $organization->id;
                }

                User::create([
                    'name' => trim($data['name']),
                    'email' => trim($data['email']),
                    'password' => Hash::make($data['password']),
                    'role' => $role,
                    'tva_number' => $role === 'entreprise' ? ($data['tva_number'] ?? null) : null,
                    'organization_account_id' => $organizationAccountId,
                    'is_active' => true,
                    'status' => 'active',
                ]);

                $imported++;
                continue;
            }

            if ($this->type === 'rendez_vous') {
                $status = match (strtolower(trim($data['status'] ?? 'en_attente'))) {
                    'confirme', 'confirmé' => 'confirme',
                    'refuse' => 'refuse',
                    'en attente', 'en_attente' => 'en_attente',
                    'en route', 'en_route' => 'en_route',
                    'sur place', 'sur_place' => 'sur_place',
                    'termine', 'terminé' => 'termine',
                    default => 'en_attente',
                };

                $validator = Validator::make(array_merge($data, ['status' => $status]), [
                    'date' => 'required|date',
                    'heure' => 'required',
                    'client_id' => 'required|exists:users,id',
                    'employe_id' => 'required|exists:users,id',
                    'status' => 'required|string|max:50',
                ]);

                if ($validator->fails()) {
                    $skipped++;
                    $errors[] = "Ligne {$lineNumber} : rendez-vous invalide.";
                    continue;
                }

                $client = User::find($data['client_id']);
                $employe = User::find($data['employe_id']);

                if (! $client || ! $client->isClient()) {
                    $skipped++;
                    $errors[] = "Ligne {$lineNumber} : client invalide.";
                    continue;
                }

                if (! $employe || ! $employe->isEmploye()) {
                    $skipped++;
                    $errors[] = "Ligne {$lineNumber} : employé invalide.";
                    continue;
                }

                RendezVous::create([
                    'date' => $data['date'],
                    'heure' => $data['heure'],
                    'client_id' => $data['client_id'],
                    'employe_id' => $data['employe_id'],
                    'status' => $status,
                ]);

                $imported++;
            }
        }

        ActivityLogger::log(count($errors) > 0 ? 'import_csv_avec_erreurs' : 'import_csv_sans_erreur', null, [
            'type' => $this->type ?? 'inconnu',
            'imported' => $imported,
            'skipped' => $skipped,
            'errors_count' => count($errors),
            'errors_preview' => array_slice($errors, 0, 10),
        ]);

        session()->flash(count($errors) > 0 ? 'warning' : 'success', "Import terminé : {$imported} ligne(s) importée(s), {$skipped} ignorée(s).");
        $this->reset('csv');
    }

    public function render()
    {
        return view('livewire.admin.import-csv');
    }
}
