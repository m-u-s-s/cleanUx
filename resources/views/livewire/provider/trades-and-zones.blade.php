{{--
    « CE QUE JE FAIS, ET OÙ ».

    Les deux listes viennent du CATALOGUE administrateur : un métier ouvert dans une nouvelle zone
    apparaît ici sans déploiement. Ce qui est coché est exactement ce que lit la requête candidate du
    dispatch — cocher change les offres reçues dans la seconde.
--}}
<div class="mx-auto max-w-3xl space-y-6">
    <header>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Mes métiers et mes zones</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
            Vous ne recevez que des missions du métier <strong>et</strong> de la zone que vous avez
            choisis. Rien d’autre ne vous sera proposé.
        </p>
    </header>

    @if ($flash)
        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200">
            {{ $flash }}
        </p>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-900">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Métiers</h2>

        @error('tradeIds')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror

        @forelse ($this->catalogue['sectors'] as $secteur)
            <div class="mt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ $secteur['name'] }}
                </p>
                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($secteur['trades'] as $metier)
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-white/10">
                            <input type="checkbox" wire:model="tradeIds" value="{{ $metier['id'] }}"
                                class="rounded border-slate-300 text-indigo-600">
                            <span class="text-sm text-slate-900 dark:text-slate-100">{{ $metier['name'] }}</span>
                            @if ($metier['allows_asap'])
                                <span class="ml-auto rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">
                                    intervention immédiate
                                </span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @empty
            {{-- Un catalogue vide se DIT. Une page muette ferait croire à une panne d'affichage. --}}
            <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">
                Aucun métier n’est encore ouvert dans ce pays. Revenez quand le catalogue aura été
                complété par l’équipe.
            </p>
        @endforelse
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-900">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Zones d’intervention</h2>

        @error('zoneIds')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror

        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($this->catalogue['zones'] as $zone)
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-white/10">
                    <input type="checkbox" wire:model="zoneIds" value="{{ $zone['id'] }}"
                        class="rounded border-slate-300 text-indigo-600">
                    <span class="text-sm text-slate-900 dark:text-slate-100">{{ $zone['name'] }}</span>
                </label>
            @endforeach
        </div>
    </section>

    <button type="button" wire:click="save"
        class="min-h-[44px] w-full rounded-xl bg-indigo-600 px-4 font-semibold text-white transition hover:bg-indigo-500">
        Enregistrer
    </button>
</div>
