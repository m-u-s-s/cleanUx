<div class="min-h-screen bg-slate-900 p-6">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white">🗺️ Centre de dispatch</h1>
            <p class="text-sm text-slate-400">Assignez et suivez les missions de votre équipe</p>
        </div>
        <div class="flex items-center gap-3">
            <input wire:model.live="filterDate" type="date"
                class="rounded-xl border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-white outline-none focus:border-amber-500">
            <select wire:model.live="filterStatus"
                class="rounded-xl border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-200 outline-none focus:border-amber-500">
                <option value="">Tous statuts</option>
                <option value="pending">En attente</option>
                <option value="assigned">Assignée</option>
                <option value="in_progress">En cours</option>
                <option value="completed">Complétée</option>
                <option value="cancelled">Annulée</option>
            </select>
        </div>
    </div>

    {{-- Missions --}}
    <div class="space-y-3">
        @forelse ($missions as $mission)
            @php
                $statusConfig = [
                    'pending'     => ['bg' => 'border-slate-600 bg-slate-800', 'dot' => 'bg-slate-500', 'label' => 'En attente'],
                    'assigned'    => ['bg' => 'border-blue-700 bg-blue-900/20', 'dot' => 'bg-blue-400', 'label' => 'Assignée'],
                    'in_progress' => ['bg' => 'border-amber-700 bg-amber-900/20', 'dot' => 'bg-amber-400', 'label' => 'En cours'],
                    'completed'   => ['bg' => 'border-green-700 bg-green-900/20', 'dot' => 'bg-green-400', 'label' => 'Complétée'],
                    'cancelled'   => ['bg' => 'border-red-900 bg-red-900/10', 'dot' => 'bg-red-500', 'label' => 'Annulée'],
                ];
                $sc = $statusConfig[$mission->status ?? 'pending'] ?? $statusConfig['pending'];
            @endphp

            <div class="rounded-2xl border {{ $sc['bg'] }} p-4 transition">
                <div class="flex flex-wrap items-center gap-4">

                    {{-- Heure + statut --}}
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-3 w-3 flex-shrink-0 rounded-full {{ $sc['dot'] }}"></div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-white">
                                {{ $mission->scheduled_at?->format('H:i') ?? '–' }}
                                <span class="ml-2 text-[10px] font-medium text-slate-400 uppercase">
                                    {{ $sc['label'] }}
                                </span>
                            </p>
                            <p class="truncate text-xs text-slate-400">
                                {{ $mission->bookingSite?->name ?? 'Site non défini' }} —
                                {{ $mission->bookingSite?->city }}
                            </p>
                        </div>
                    </div>

                    {{-- Prestataires assignés --}}
                    <div class="flex items-center gap-2">
                        @if (($mission->assignments ?? collect())->isNotEmpty())
                            <div class="flex -space-x-2">
                                @foreach (($mission->assignments ?? collect())->take(4) as $a)
                                    <img src="{{ $a->provider?->profile_photo_url }}"
                                         title="{{ $a->provider?->name }}"
                                         class="h-8 w-8 rounded-full border-2 border-slate-900 object-cover">
                                @endforeach
                            </div>
                        @else
                            <span class="text-xs text-slate-500 italic">Non assignée</span>
                        @endif
                    </div>

                    {{-- Bouton assigner --}}
                    <div class="ml-auto flex items-center gap-2">
                        @if (in_array($mission->status ?? 'pending', ['pending', 'assigned']))
                            <button wire:click="startAssign({{ $mission->id }})"
                                class="rounded-xl border border-amber-600 px-3 py-1.5 text-xs font-semibold text-amber-400 hover:bg-amber-900/20 transition">
                                {{ ($mission->assignments ?? collect())->isEmpty() ? '+ Assigner' : '↻ Réassigner' }}
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Panel d'assignation --}}
                @if ($assigningId === $mission->id)
                    <div class="mt-4 rounded-xl border border-slate-600 bg-slate-900 p-4">
                        <p class="mb-3 text-sm font-semibold text-white">Assigner à un membre de l'équipe</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 max-h-48 overflow-y-auto">
                            @foreach ($availableWorkers as $worker)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border p-2 transition
                                    {{ $assigneeId == $worker->user_id
                                        ? 'border-amber-500 bg-amber-900/30'
                                        : 'border-slate-600 hover:border-slate-500' }}">
                                    <input type="radio" wire:model="assigneeId" value="{{ $worker->user_id }}" class="sr-only">
                                    <img src="{{ $worker->user?->profile_photo_url }}"
                                         class="h-8 w-8 flex-shrink-0 rounded-full object-cover border border-slate-600">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-white">{{ $worker->user?->name }}</p>
                                        <p class="text-[10px] text-slate-400">{{ $worker->role->label() }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button wire:click="cancelAssign"
                                class="flex-1 rounded-xl border border-slate-600 py-2 text-xs text-slate-300 hover:bg-slate-800">
                                Annuler
                            </button>
                            <button wire:click="confirmAssign"
                                :disabled="{{ is_null($assigneeId) ? 'true' : 'false' }}"
                                class="flex-1 rounded-xl bg-amber-600 py-2 text-xs font-bold text-white hover:bg-amber-700 disabled:opacity-50">
                                ✓ Confirmer l'assignation
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center rounded-2xl border-2 border-dashed border-slate-700 py-16 text-center">
                <p class="text-4xl mb-3">🗺️</p>
                <p class="text-slate-400">Aucune mission pour cette période</p>
            </div>
        @endforelse
    </div>
</div>
