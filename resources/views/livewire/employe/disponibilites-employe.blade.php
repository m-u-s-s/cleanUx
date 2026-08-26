@php
    /*
     * L'INDEX D'UN JOUR ET SON RANG D'AFFICHAGE SONT DEUX CHOSES.
     *
     * Côté serveur, `availability_slots.weekday` suit la convention de Carbon : 0 = dimanche. Une
     * semaine européenne, elle, commence le lundi. Confondre les deux décale les sept étiquettes
     * d'un cran — c'est le défaut qu'on vient de corriger dans l'application mobile.
     */
    $nomsDeJour = [
        0 => __('Dimanche'), 1 => __('Lundi'), 2 => __('Mardi'), 3 => __('Mercredi'),
        4 => __('Jeudi'), 5 => __('Vendredi'), 6 => __('Samedi'),
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">{{ __('Mes disponibilités') }}</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400">
                {{ __('Votre semaine type vous rend joignable. Fermer une date précise ne la modifie pas.') }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- ── Formulaire : un créneau de la semaine type ─────────────────────────────── --}}
        <div class="space-y-4 rounded-2xl border bg-white p-4 shadow-sm xl:col-span-1 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="font-semibold text-gray-900 dark:text-slate-100">
                {{ $editingId ? __('Modifier le créneau') : __('Ajouter un créneau') }}
            </h2>
            <p class="text-xs text-gray-500 dark:text-slate-400">
                {{ __('Il se répète toutes les semaines, jusqu’à ce que vous le retiriez.') }}
            </p>

            <div>
                <label class="mb-1 block text-sm text-gray-600 dark:text-slate-300" for="weekday">{{ __('Jour') }}</label>
                <select id="weekday" wire:model="weekday" class="w-full rounded-lg border px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    @foreach ([1, 2, 3, 4, 5, 6, 0] as $jour)
                        <option value="{{ $jour }}">{{ $nomsDeJour[$jour] }}</option>
                    @endforeach
                </select>
                @error('weekday') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-sm text-gray-600 dark:text-slate-300" for="heure_debut">{{ __('Début') }}</label>
                    <input id="heure_debut" type="time" wire:model="heure_debut" class="w-full rounded-lg border px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    @error('heure_debut') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm text-gray-600 dark:text-slate-300" for="heure_fin">{{ __('Fin') }}</label>
                    <input id="heure_fin" type="time" wire:model="heure_fin" class="w-full rounded-lg border px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    @error('heure_fin') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-2">
                <button wire:click="save" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white">
                    {{ $editingId ? __('Mettre à jour') : __('Ajouter') }}
                </button>
                <button wire:click="resetForm" class="rounded-lg border px-4 py-2 text-sm text-gray-700 dark:border-slate-600 dark:text-slate-200">
                    {{ __('Réinitialiser') }}
                </button>
            </div>
        </div>

        {{-- ── La semaine type ────────────────────────────────────────────────────────── --}}
        <div class="space-y-4 rounded-2xl border bg-white p-4 shadow-sm xl:col-span-2 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="font-semibold text-gray-900 dark:text-slate-100">{{ __('Semaine type') }}</h2>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @foreach ([1, 2, 3, 4, 5, 6, 0] as $jour)
                    @php $creneaux = $slotsByWeekday[$jour] ?? collect(); @endphp
                    <div class="space-y-2 rounded-2xl border p-4 dark:border-slate-700">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-semibold text-gray-900 dark:text-slate-100">{{ $nomsDeJour[$jour] }}</p>
                            @if($creneaux->isEmpty())
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-slate-700 dark:text-slate-300">
                                    {{ __('Fermé') }}
                                </span>
                            @endif
                        </div>

                        @foreach($creneaux as $slot)
                            <div class="flex items-center justify-between gap-2 rounded-xl border bg-gray-50 p-2.5 dark:border-slate-600 dark:bg-slate-900/60">
                                <p class="text-sm font-medium text-gray-800 dark:text-slate-100">
                                    {{ substr($slot->start_time, 0, 5) }} → {{ substr($slot->end_time, 0, 5) }}
                                </p>
                                <div class="flex gap-2">
                                    {{-- Ces deux actions mesuraient 51 x 16 et 42 x 16 pixels : un texte nu,
                                         sans zone de clic autour du mot, au-dessous du minimum tactile. Un
                                         prestataire retire un créneau depuis son téléphone, entre deux
                                         interventions. --}}
                                    <button wire:click="edit({{ $slot->id }})"
                                            class="brio-btn-ligne brio-btn-ligne-accent">{{ __('Modifier') }}</button>
                                    <button wire:click="delete({{ $slot->id }})"
                                            wire:confirm="{{ __('Retirer ce créneau de toutes les semaines ?') }}"
                                            class="brio-btn-ligne brio-btn-ligne-danger">{{ __('Retirer') }}</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Les jours fermés, datés ────────────────────────────────────────────────────── --}}
    <div class="space-y-4 rounded-2xl border bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900 dark:text-slate-100">{{ __('Jours fermés') }}</h2>
                <p class="text-xs text-gray-500 dark:text-slate-400">
                    {{ __('Semaine du :date', ['date' => \Carbon\Carbon::parse($weekStart)->translatedFormat('d F Y')]) }}
                    — {{ __('une date fermée l’emporte sur la semaine type, sans la modifier.') }}
                </p>
            </div>
            <div class="flex gap-2">
                <button wire:click="previousWeek" class="rounded-lg border px-4 py-2 text-sm text-gray-700 dark:border-slate-600 dark:text-slate-200">{{ __('← Semaine précédente') }}</button>
                <button wire:click="nextWeek" class="rounded-lg border px-4 py-2 text-sm text-gray-700 dark:border-slate-600 dark:text-slate-200">{{ __('Semaine suivante →') }}</button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach($weekDays as $day)
                @php
                    $cle = $day->toDateString();
                    $ferme = $closedDays[$cle] ?? null;
                    $creneauxDuJour = $slotsByWeekday[(int) $day->dayOfWeek] ?? collect();
                @endphp
                <div class="space-y-2 rounded-2xl border p-4 {{ $day->isToday() ? 'border-blue-300 ring-2 ring-blue-200 dark:border-blue-500 dark:ring-blue-900' : 'dark:border-slate-700' }}">
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-slate-100">{{ $nomsDeJour[(int) $day->dayOfWeek] }}</p>
                        <p class="text-xs text-gray-500 dark:text-slate-400">{{ $day->translatedFormat('d/m/Y') }}</p>
                    </div>

                    @if($ferme)
                        <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ __('Fermé') }}</p>
                        @if($ferme->reason)
                            <p class="text-xs text-gray-500 dark:text-slate-400">{{ $ferme->reason }}</p>
                        @endif
                        <button wire:click="reopenDay({{ $ferme->id }})" class="text-xs text-blue-600 dark:text-blue-400">{{ __('Rouvrir') }}</button>
                    @else
                        <p class="text-xs text-gray-600 dark:text-slate-300">
                            @forelse($creneauxDuJour as $slot)
                                {{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}@if(! $loop->last), @endif
                            @empty
                                {{ __('Aucun créneau ce jour-là.') }}
                            @endforelse
                        </p>
                        <button wire:click="closeDay('{{ $cle }}')"
                                wire:confirm="{{ __('Fermer cette journée ? Votre semaine type reste inchangée.') }}"
                                class="rounded bg-red-50 px-2 py-1 text-xs text-red-600 dark:bg-red-500/15 dark:text-red-300">
                            {{ __('Fermer ce jour') }}
                        </button>
                    @endif

                    <div class="border-t pt-2 dark:border-slate-700">
                        @livewire('modifier-limite-jour', [
                            'date' => $cle,
                            'user_id' => auth()->id(),
                            'fromAdmin' => false,
                        ], key('dispo-limit-'.$cle.'-'.auth()->id()))
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
