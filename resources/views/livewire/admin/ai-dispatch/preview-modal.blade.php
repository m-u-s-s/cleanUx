@if($previewRdvId)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
        <div class="w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">
                        Scoring IA Dispatch
                    </h3>
                    <p class="text-sm text-slate-500">
                        Plus le score est haut, meilleur est le choix.
                    </p>
                </div>

                <button type="button" wire:click="closePreview" class="text-slate-500 hover:text-slate-900">
                    ✕
                </button>
            </div>

            <div class="mt-4 space-y-3">
                @forelse($ranking as $row)
                    <div class="rounded-2xl border p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-slate-900">
                                    {{ $row['name'] }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    Employé #{{ $row['employee_id'] }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-3xl font-bold text-indigo-700">
                                    {{ $row['score'] }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    score total
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs md:grid-cols-4">
                            @foreach($row['details'] as $label => $value)
                                <div class="rounded-xl border bg-slate-50 p-2">
                                    <p class="text-slate-500">{{ ucfirst($label) }}</p>
                                    <p class="font-semibold text-slate-900">{{ $value }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">
                        Aucun employé disponible.
                    </p>
                @endforelse
            </div>
        </div>
    </div>
@endif
