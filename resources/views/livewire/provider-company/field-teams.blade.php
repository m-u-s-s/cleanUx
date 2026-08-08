<div class="mx-auto max-w-4xl">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Équipes terrain</h1>
        <p class="mt-1 text-sm text-slate-500">
            Vos agences et leurs responsables. Jusqu'ici, seul un administrateur pouvait les créer.
        </p>
    </header>

    {{-- Création --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Nom de l'équipe</span>
                <input type="text" wire:model="nom" placeholder="Agence Nord"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('nom')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Missions simultanées</span>
                <input type="number" min="1" max="50" wire:model="capaciteMax"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('capaciteMax')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Zone de service</span>
                <select wire:model="zoneId"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="">Aucune</option>
                    @foreach ($zones as $zone)
                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Responsable</span>
                <select wire:model="chefId"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
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
    <div class="mb-2 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-900">
                {{ $equipe->name }}
                @if ($equipe->status === 'archived')
                <span class="ml-1 text-xs font-normal text-slate-400">— archivée</span>
                @endif
            </p>
            <p class="truncate text-xs text-slate-500">
                {{ $equipe->serviceZone?->name ?? 'Aucune zone' }}
                · {{ $equipe->teamLead?->name ?? 'Sans responsable' }}
                · {{ $equipe->max_concurrent_missions }} mission(s) en parallèle
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            {{--
                LA COMPOSITION, ATTEIGNABLE.

                `field_team_members` n'était manipulable que depuis l'administration de la
                plateforme : une société qui créait son équipe ici ne pouvait pas la peupler, et une
                équipe VIDE ne peut recevoir aucune mission. L'écran n'affichait que des coquilles.
            --}}
            <button type="button" wire:click="ouvrirLaComposition({{ $equipe->id }})"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">
                {{ $equipeOuverteId === $equipe->id ? 'Fermer' : 'Composition' }}
                ({{ $equipe->activeMembers->count() }})
            </button>

            @if ($equipe->status !== 'archived')
            <button type="button" wire:click="archiver({{ $equipe->id }})"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">
                Archiver
            </button>
            @endif
        </div>
    </div>

    @if ($equipeOuverteId === $equipe->id)
    <div class="mb-3 ml-4 border-l-2 border-slate-200 pl-4">
        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Membres</p>

        @forelse ($equipe->activeMembers as $membre)
        <div class="flex items-center justify-between gap-3 py-1">
            <span class="truncate text-sm text-slate-700">
                {{ $membre->user?->name ?? 'Utilisateur supprimé' }}
                @if ((int) $equipe->team_lead_user_id === (int) $membre->user_id)
                <span class="text-xs text-slate-400">— responsable</span>
                @endif
            </span>
            <button type="button" wire:click="retirerMembre({{ $equipe->id }}, {{ $membre->user_id }})"
                class="shrink-0 text-xs text-slate-500 underline hover:text-slate-700">
                Retirer
            </button>
        </div>
        @empty
        <p class="text-xs text-slate-400">
            Aucun membre — une équipe vide ne peut recevoir aucune mission.
        </p>
        @endforelse

        @php
            $dejaMembres = $equipe->activeMembers->pluck('user_id')->all();
            $recrutables = $collegues->reject(fn ($c) => in_array($c->user_id, $dejaMembres, true));
        @endphp

        @if ($recrutables->isNotEmpty())
        <p class="mb-1 mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">Ajouter un collègue</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($recrutables as $collegue)
            <button type="button" wire:click="ajouterMembre({{ $equipe->id }}, {{ $collegue->user_id }})"
                class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-100">
                + {{ $collegue->user?->name ?? '—' }}
            </button>
            @endforeach
        </div>
        @endif
    </div>
    @endif
    @empty
    <p class="text-sm text-slate-500">Aucune équipe pour le moment.</p>
    @endforelse
</div>
