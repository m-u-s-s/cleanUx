<div class="mx-auto max-w-4xl px-4 py-6">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-white">Équipes terrain</h1>
        <p class="mt-1 text-sm text-slate-400">
            Vos agences et leurs responsables. Jusqu'ici, seul un administrateur pouvait les créer.
        </p>
    </header>

    {{-- Création --}}
    <div class="mb-8 rounded-2xl border border-slate-700 bg-slate-800 p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-white">Nom de l'équipe</span>
                <input type="text" wire:model="nom" placeholder="Agence Nord"
                    class="w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
                @error('nom')
                <p class="mt-1 text-xs font-semibold text-rose-400">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-white">Missions simultanées</span>
                <input type="number" min="1" max="50" wire:model="capaciteMax"
                    class="w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
                @error('capaciteMax')
                <p class="mt-1 text-xs font-semibold text-rose-400">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-white">Zone de service</span>
                <select wire:model="zoneId"
                    class="w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
                    <option value="">Aucune</option>
                    @foreach ($zones as $zone)
                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-white">Responsable</span>
                <select wire:model="chefId"
                    class="w-full rounded-lg border-slate-600 bg-slate-900 text-sm text-white">
                    <option value="">À désigner</option>
                    @foreach ($collegues as $membre)
                    <option value="{{ $membre->user_id }}">{{ $membre->user?->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <button type="button" wire:click="creer"
            class="mt-4 w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto">
            Créer l'équipe
        </button>
    </div>

    {{-- Liste --}}
    @forelse ($equipes as $equipe)
    <div class="mb-2 flex items-center justify-between gap-3 rounded-xl border border-slate-700 bg-slate-800 px-4 py-3">
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-white">
                {{ $equipe->name }}
                @if ($equipe->status === 'archived')
                <span class="ml-1 text-xs font-normal text-slate-500">— archivée</span>
                @endif
            </p>
            <p class="truncate text-xs text-slate-400">
                {{ $equipe->serviceZone?->name ?? 'Aucune zone' }}
                · {{ $equipe->teamLead?->name ?? 'Sans responsable' }}
                · {{ $equipe->max_concurrent_missions }} mission(s) en parallèle
            </p>
        </div>

        @if ($equipe->status !== 'archived')
        <button type="button" wire:click="archiver({{ $equipe->id }})"
            class="shrink-0 rounded-lg border border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-700">
            Archiver
        </button>
        @endif
    </div>
    @empty
    <p class="text-sm text-slate-400">Aucune équipe pour le moment.</p>
    @endforelse
</div>
