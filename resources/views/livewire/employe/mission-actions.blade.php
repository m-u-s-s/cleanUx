<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Actions mission</h3>
            <p class="text-sm text-slate-500">
                Statut actuel :
                <span class="font-medium text-slate-800">{{ $mission->status }}</span>
            </p>
        </div>
    </div>

    @if ($successMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ $successMessage }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="grid gap-3 md:grid-cols-2">
        <button
            wire:click="setEnRoute"
            type="button"
            class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
            @disabled(! in_array($mission->status, ['planned', 'assigned']))
        >
            En route
        </button>

        <button
            wire:click="setArrived"
            type="button"
            class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
            @disabled(! in_array($mission->status, ['en_route', 'assigned']))
        >
            Arrivé
        </button>
    </div>

    <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="font-medium text-slate-900">Commencer la mission</h4>
            @if ($generatedStartCode)
                <span class="rounded-lg bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-800">
                    Code début : {{ $generatedStartCode }}
                </span>
            @endif
        </div>

        <div class="flex gap-2">
            <input
                wire:model.defer="startCode"
                type="text"
                inputmode="numeric"
                maxlength="6"
                placeholder="Code début"
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none"
            >
            <button
                wire:click="startMission"
                type="button"
                class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
                @disabled($mission->status !== 'arrived')
            >
                Commencer
            </button>
        </div>

        @error('startCode')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="font-medium text-slate-900">Terminer la mission</h4>

            <button
                wire:click="prepareEndCode"
                type="button"
                class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                @disabled(! in_array($mission->status, ['started', 'paused']))
            >
                Générer code fin
            </button>
        </div>

        @if ($generatedEndCode)
            <div class="rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-700">
                <span class="font-semibold">Code fin :</span> {{ $generatedEndCode }}
            </div>
        @endif

        <div class="flex gap-2">
            <input
                wire:model.defer="endCode"
                type="text"
                inputmode="numeric"
                maxlength="6"
                placeholder="Code fin"
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none"
            >
            <button
                wire:click="finishMission"
                type="button"
                class="rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
                @disabled(! in_array($mission->status, ['started', 'paused']))
            >
                Terminer
            </button>
        </div>

        @error('endCode')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>