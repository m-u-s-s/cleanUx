<div class="py-6 max-w-4xl mx-auto px-4">

    <div class="mb-6">
        <p class="text-sm font-bold uppercase text-indigo-600">B2B Enterprise</p>
        <h1 class="text-2xl font-black text-slate-900">Import bulk de réservations</h1>
        <p class="text-sm text-slate-400">Upload un fichier CSV pour créer plusieurs bookings d'un coup.</p>
    </div>

    @if (! $report)
        <div class="rounded-2xl bg-white border shadow-sm p-6 mb-4">
            <h2 class="text-sm font-bold text-slate-900 mb-3">📋 Format CSV attendu</h2>
            <div class="rounded-lg bg-white text-emerald-700 p-3 font-mono text-xs overflow-x-auto">
                <pre>site_code,trade_code,scheduled_at,duration_minutes,budget_max_eur,notes,external_ref
PARIS-RIVOLI,nettoyage_bureaux,2026-06-01 09:00,180,80,Nettoyage hebdo,REF-001
PARIS-LAFAYETTE,nettoyage_bureaux,2026-06-01 14:00,120,60,,REF-002
LYON-PART-DIEU,jardinage,2026-06-02 10:00,240,150,Tonte + désherbage,REF-003</pre>
            </div>
            <ul class="text-xs text-slate-400 mt-3 space-y-1">
                <li>• <code>site_code</code> : code de votre site (doit exister dans votre org)</li>
                <li>• <code>trade_code</code> : code du métier Brio</li>
                <li>• <code>scheduled_at</code> : date+heure ISO 8601 (futur uniquement)</li>
                <li>• <code>external_ref</code> : votre référence interne (idempotency, optionnel)</li>
            </ul>
        </div>

        <div class="rounded-2xl bg-white border shadow-sm p-6">
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-bold text-slate-600">Fichier CSV *</label>
                    <input wire:model="csvFile" type="file" accept=".csv,.txt" class="w-full text-sm mt-1 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:px-3 file:py-2 file:font-semibold">
                    @error('csvFile') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
                    <p class="text-xs text-slate-500 mt-1">Max 2 MB · header obligatoire sur la 1ère ligne</p>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-600">Séparateur</label>
                    <select wire:model="separator" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                        <option value=",">Virgule (,)</option>
                        <option value=";">Point-virgule (;)</option>
                    </select>
                </div>

                @if ($error)
                    <div class="rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700">{{ $error }}</div>
                @endif

                <button wire:click="import" @disabled($importing)
                        class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white py-3 text-sm font-bold disabled:opacity-50">
                    <span wire:loading.remove wire:target="import">Importer le fichier</span>
                    <span wire:loading wire:target="import">🔄 Import en cours...</span>
                </button>
                <p class="text-xs text-slate-500 text-center">Les erreurs par ligne sont reportées sans bloquer les lignes valides.</p>
            </div>
        </div>
    @else
        {{-- Report --}}
        <div class="rounded-2xl bg-white border shadow-sm p-6">
            <div class="text-center mb-6">
                @if ($report['ok'] && $report['created_count'] > 0)
                    <p class="text-5xl mb-2">✅</p>
                    <h2 class="text-xl font-bold text-emerald-700">Import terminé</h2>
                @elseif ($report['errors_count'] ?? 0 > 0)
                    <p class="text-5xl mb-2">⚠️</p>
                    <h2 class="text-xl font-bold text-amber-700">Import partiel</h2>
                @else
                    <p class="text-5xl mb-2">❌</p>
                    <h2 class="text-xl font-bold text-rose-700">Import échoué</h2>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3 mb-6">
                <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-center">
                    <p class="text-xs uppercase font-bold text-emerald-700">Créés</p>
                    <p class="text-3xl font-black text-emerald-700">{{ $report['created_count'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-center">
                    <p class="text-xs uppercase font-bold text-rose-700">Erreurs</p>
                    <p class="text-3xl font-black text-rose-700">{{ $report['errors_count'] ?? 0 }}</p>
                </div>
            </div>

            @if (!empty($report['errors']))
                <div class="mb-4">
                    <h3 class="text-xs uppercase font-bold text-slate-400 mb-2">Détails erreurs</h3>
                    <div class="rounded-lg bg-slate-50 border max-h-64 overflow-y-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-100">
                                <tr><th class="px-2 py-1 text-left">Ligne</th><th class="px-2 py-1 text-left">Erreur</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($report['errors'] as $err)
                                    <tr class="border-t">
                                        <td class="px-2 py-1 font-mono text-slate-400">{{ $err['row'] }}</td>
                                        <td class="px-2 py-1 text-rose-600">{{ $err['error'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <button wire:click="reset_" class="w-full rounded-lg border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Importer un autre fichier
            </button>
        </div>
    @endif
</div>
