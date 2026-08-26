@php
    // 0 = dimanche côté serveur ; l'affichage, lui, commence au lundi. Deux notions distinctes.
    $nomsDeJour = [
        0 => 'Dimanche', 1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi',
        4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi',
    ];
    $ordreSemaine = [1, 2, 3, 4, 5, 6, 0];
    $totalCreneaux = $slotsByWeekday->sum(fn ($groupe) => $groupe->count());
@endphp

<div class="py-6">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <a href="{{ route('admin.availability.center') }}" class="text-sm font-semibold text-indigo-600">← {{ __('Centre disponibilités') }}</a>
                <h1 class="mt-1 text-2xl font-black text-slate-900 dark:text-slate-100">{{ $provider->name }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $provider->email }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($totalCreneaux === 0)
                    <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-700 dark:bg-red-500/15 dark:text-red-300">
                        {{ __('Aucune disponibilité — injoignable à la planification') }}
                    </span>
                    <button wire:click="applyDefaultWeek"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">
                        {{ __('Appliquer la semaine par défaut') }}
                    </button>
                @else
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                        {{ trans_choice(':count créneau|:count créneaux', $totalCreneaux, ['count' => $totalCreneaux]) }}
                    </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Formulaire --}}
            <div class="space-y-4 rounded-2xl border bg-white p-4 shadow-sm xl:col-span-1 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">
                    {{ $editingId ? __('Modifier le créneau') : __('Ajouter un créneau') }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Il se répète toutes les semaines. La modification est tracée au nom de ce prestataire.') }}
                </p>

                <div>
                    <label class="mb-1 block text-sm text-slate-600 dark:text-slate-300" for="weekday">{{ __('Jour') }}</label>
                    <select id="weekday" wire:model="weekday" class="w-full rounded-lg border px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        @foreach($ordreSemaine as $jour)
                            <option value="{{ $jour }}">{{ $nomsDeJour[$jour] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm text-slate-600 dark:text-slate-300" for="heure_debut">{{ __('Début') }}</label>
                        <input id="heure_debut" type="time" wire:model="heure_debut" class="w-full rounded-lg border px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        @error('heure_debut') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-600 dark:text-slate-300" for="heure_fin">{{ __('Fin') }}</label>
                        <input id="heure_fin" type="time" wire:model="heure_fin" class="w-full rounded-lg border px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        @error('heure_fin') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex gap-2">
                    <button wire:click="save" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">
                        {{ $editingId ? __('Mettre à jour') : __('Ajouter') }}
                    </button>
                    <button wire:click="resetForm" class="rounded-lg border px-4 py-2 text-sm text-slate-700 dark:border-slate-600 dark:text-slate-200">
                        {{ __('Réinitialiser') }}
                    </button>
                </div>
            </div>

            {{-- Semaine type --}}
            <div class="space-y-4 rounded-2xl border bg-white p-4 shadow-sm xl:col-span-2 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('Semaine type') }}</h2>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach($ordreSemaine as $jour)
                        @php $creneaux = $slotsByWeekday[$jour] ?? collect(); @endphp
                        <div class="space-y-2 rounded-2xl border p-4 dark:border-slate-700" wire:key="jour-{{ $jour }}">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $nomsDeJour[$jour] }}</p>
                                @if($creneaux->isEmpty())
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ __('Fermé') }}</span>
                                @endif
                            </div>

                            @foreach($creneaux as $slot)
                                <div class="flex items-center justify-between gap-2 rounded-xl border bg-slate-50 p-2.5 dark:border-slate-600 dark:bg-slate-900/60" wire:key="slot-{{ $slot->id }}">
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                                        {{ substr($slot->start_time, 0, 5) }} → {{ substr($slot->end_time, 0, 5) }}
                                    </p>
                                    <div class="flex gap-2">
                                        <button wire:click="edit({{ $slot->id }})" class="text-xs text-indigo-600 dark:text-indigo-400">{{ __('Modifier') }}</button>
                                        <button wire:click="delete({{ $slot->id }})"
                                                wire:confirm="{{ __('Retirer ce créneau de toutes les semaines de ce prestataire ?') }}"
                                                class="text-xs text-red-600 dark:text-red-400">{{ __('Retirer') }}</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Jours fermés --}}
        <div class="space-y-4 rounded-2xl border bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('Jours fermés') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Semaine du :date', ['date' => \Carbon\Carbon::parse($weekStart)->translatedFormat('d F Y')]) }}
                        — {{ __('une date fermée l’emporte sur la semaine type, sans la modifier.') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button wire:click="previousWeek" class="rounded-lg border px-4 py-2 text-sm text-slate-700 dark:border-slate-600 dark:text-slate-200">{{ __('← Semaine précédente') }}</button>
                    <button wire:click="nextWeek" class="rounded-lg border px-4 py-2 text-sm text-slate-700 dark:border-slate-600 dark:text-slate-200">{{ __('Semaine suivante →') }}</button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach($weekDays as $day)
                    @php
                        $cle = $day->toDateString();
                        $ferme = $closedDays[$cle] ?? null;
                    @endphp
                    <div class="space-y-2 rounded-2xl border p-4 dark:border-slate-700" wire:key="date-{{ $cle }}">
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $nomsDeJour[(int) $day->dayOfWeek] }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $day->translatedFormat('d/m/Y') }}</p>
                        </div>

                        @if($ferme)
                            <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ __('Fermé') }}</p>
                            @if($ferme->reason)<p class="text-xs text-slate-500 dark:text-slate-400">{{ $ferme->reason }}</p>@endif
                            <button wire:click="reopenDay({{ $ferme->id }})" class="text-xs text-indigo-600 dark:text-indigo-400">{{ __('Rouvrir') }}</button>
                        @else
                            <button wire:click="closeDay('{{ $cle }}')"
                                    wire:confirm="{{ __('Fermer cette journée pour ce prestataire ?') }}"
                                    class="rounded bg-red-50 px-2 py-1 text-xs text-red-600 dark:bg-red-500/15 dark:text-red-300">
                                {{ __('Fermer ce jour') }}
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
