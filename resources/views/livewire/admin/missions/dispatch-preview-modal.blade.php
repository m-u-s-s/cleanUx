@if($dispatchPreviewRdvId)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
        <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Scoring dispatch</h3>
                    <p class="text-sm text-slate-500">Classement des employés disponibles.</p>
                </div>

                <button wire:click="closeDispatchPreview" class="text-slate-500 hover:text-slate-800">
                    ✕
                </button>
            </div>

            <div class="space-y-2">
                @forelse($dispatchPreview as $row)
                    <div class="flex items-center justify-between rounded-xl border p-3">
                        <div>
                            <p class="font-medium text-slate-900">{{ $row['name'] }}</p>
                            <p class="text-xs {{ $row['available'] ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $row['available'] ? 'Disponible' : 'Indisponible' }}
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="text-xl font-bold text-indigo-700">{{ $row['score'] }}</p>
                            <p class="text-xs text-slate-500">score</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Aucun employé trouvé.</p>
                @endforelse
            </div>
        </div>
    </div>
@endif
