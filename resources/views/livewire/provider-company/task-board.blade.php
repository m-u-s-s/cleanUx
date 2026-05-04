<div class="min-h-screen bg-slate-50 p-6">

    {{-- ── Header ── --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900">📋 Tâches d'équipe</h1>
            <p class="text-sm text-slate-500">Gérez et assignez les tâches de votre organisation</p>
        </div>

        <div class="flex items-center gap-3">
            {{-- Filtre membre --}}
            <select wire:model.live="filterMember"
                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm">
                <option value="">Tous les membres</option>
                @foreach ($members as $m)
                    <option value="{{ $m->user_id }}">{{ $m->user->name }}</option>
                @endforeach
            </select>

            {{-- Filtre priorité --}}
            <select wire:model.live="filterPrio"
                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm">
                <option value="">Toutes priorités</option>
                <option value="urgent">🔴 Urgent</option>
                <option value="high">🟠 Haute</option>
                <option value="medium">🔵 Moyenne</option>
                <option value="low">⚪ Basse</option>
            </select>

            <button wire:click="$set('showCreate', true)"
                class="flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                + Nouvelle tâche
            </button>
        </div>
    </div>

    {{-- ── Colonnes Kanban ── --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

        @php
            $columns = [
                ['key' => 'todo',        'label' => 'À faire',   'icon' => '⭕', 'color' => 'slate',  'tasks' => $todoTasks,       'count' => $todoTasks->count()],
                ['key' => 'in_progress', 'label' => 'En cours',  'icon' => '🔄', 'color' => 'blue',   'tasks' => $inProgressTasks, 'count' => $inProgressTasks->count()],
                ['key' => 'done',        'label' => 'Terminé',   'icon' => '✅', 'color' => 'green',  'tasks' => $doneTasks,       'count' => $doneTasks->count()],
            ];
        @endphp

        @foreach ($columns as $col)
            <div class="flex flex-col gap-3">

                {{-- Header colonne --}}
                <div class="flex items-center gap-2 px-1">
                    <span class="text-base">{{ $col['icon'] }}</span>
                    <h2 class="font-bold text-slate-700">{{ $col['label'] }}</h2>
                    <span class="ml-auto rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-600">
                        {{ $col['count'] }}
                    </span>
                </div>

                {{-- Cards --}}
                <div class="flex flex-col gap-2 min-h-32">
                    @forelse ($col['tasks'] as $task)
                        <div class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md
                            {{ $task->isOverdue() ? 'border-l-4 border-l-red-500' : '' }}">

                            {{-- Priorité + titre --}}
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    @php
                                        $prioBadge = match($task->priority) {
                                            'urgent' => 'bg-red-100 text-red-700',
                                            'high'   => 'bg-orange-100 text-orange-700',
                                            'medium' => 'bg-blue-100 text-blue-700',
                                            default  => 'bg-slate-100 text-slate-500',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $prioBadge }}">
                                        {{ $task->priority }}
                                    </span>
                                </div>

                                @if ($task->isOverdue())
                                    <span class="text-[10px] font-bold text-red-600">⚠️ En retard</span>
                                @endif
                            </div>

                            <p class="text-sm font-semibold text-slate-900 leading-snug">{{ $task->title }}</p>

                            @if ($task->description)
                                <p class="mt-1 text-xs text-slate-500 line-clamp-2">{{ $task->description }}</p>
                            @endif

                            {{-- Assignés --}}
                            @if ($task->assignees->isNotEmpty())
                                <div class="mt-3 flex items-center gap-1">
                                    <div class="flex -space-x-1.5">
                                        @foreach ($task->assignees->take(4) as $assignee)
                                            <img src="{{ $assignee->profile_photo_url }}"
                                                 alt="{{ $assignee->name }}"
                                                 title="{{ $assignee->name }}"
                                                 class="h-6 w-6 rounded-full border-2 border-white object-cover">
                                        @endforeach
                                        @if ($task->assignees->count() > 4)
                                            <div class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-slate-100 text-[9px] font-bold text-slate-500">
                                                +{{ $task->assignees->count() - 4 }}
                                            </div>
                                        @endif
                                    </div>
                                    @if ($task->due_date)
                                        <span class="ml-auto text-[10px] text-slate-400">
                                            📅 {{ $task->due_date->format('d/m') }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Actions --}}
                            <div class="mt-3 flex items-center gap-1 border-t border-slate-100 pt-2 opacity-0 transition group-hover:opacity-100">
                                @if ($col['key'] !== 'todo')
                                    <button wire:click="updateStatus({{ $task->id }}, 'todo')"
                                        class="rounded-lg px-2 py-1 text-[10px] font-medium text-slate-500 hover:bg-slate-100">
                                        ← À faire
                                    </button>
                                @endif
                                @if ($col['key'] !== 'in_progress')
                                    <button wire:click="updateStatus({{ $task->id }}, 'in_progress')"
                                        class="rounded-lg px-2 py-1 text-[10px] font-medium text-blue-600 hover:bg-blue-50">
                                        🔄 En cours
                                    </button>
                                @endif
                                @if ($col['key'] !== 'done')
                                    <button wire:click="updateStatus({{ $task->id }}, 'done')"
                                        class="rounded-lg px-2 py-1 text-[10px] font-medium text-green-600 hover:bg-green-50">
                                        ✅ Terminer
                                    </button>
                                @endif
                                <button wire:click="deleteTask({{ $task->id }})"
                                    wire:confirm="Supprimer cette tâche ?"
                                    class="ml-auto rounded-lg px-2 py-1 text-[10px] text-red-400 hover:bg-red-50">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center text-slate-400">
                            <p class="text-2xl mb-1">{{ $col['icon'] }}</p>
                            <p class="text-xs">Aucune tâche</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- ── Modal création ── --}}
@if ($showCreate)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-lg font-black text-slate-900">Nouvelle tâche</h3>
            </div>

            <div class="space-y-4 p-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Titre *</label>
                    <input wire:model="title" type="text" placeholder="Description de la tâche…"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    @error('title') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Description</label>
                    <textarea wire:model="description" rows="3" placeholder="Détails optionnels…"
                        class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Priorité</label>
                        <select wire:model="priority"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500">
                            <option value="low">⚪ Basse</option>
                            <option value="medium">🔵 Moyenne</option>
                            <option value="high">🟠 Haute</option>
                            <option value="urgent">🔴 Urgente</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Échéance</label>
                        <input wire:model="dueDate" type="date"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Assigner à</label>
                    <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto">
                        @foreach ($members as $m)
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2 cursor-pointer hover:bg-slate-50
                                {{ in_array($m->user_id, $assigneeIds) ? 'border-blue-500 bg-blue-50' : '' }}">
                                <input type="checkbox" wire:model="assigneeIds" value="{{ $m->user_id }}" class="rounded">
                                <img src="{{ $m->user->profile_photo_url }}"
                                     class="h-6 w-6 rounded-full object-cover">
                                <span class="text-xs font-medium text-slate-700 truncate">{{ $m->user->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex gap-3 border-t border-slate-100 p-4">
                <button wire:click="$set('showCreate', false)"
                    class="flex-1 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Annuler
                </button>
                <button wire:click="createTask"
                    class="flex-1 rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">
                    Créer la tâche
                </button>
            </div>
        </div>
    </div>
@endif
