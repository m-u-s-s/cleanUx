<div class="py-6">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Availability v2</p>
                <h1 class="text-2xl font-black text-slate-900 dark:text-slate-100">{{ __('Centre disponibilités prestataires') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Fuseau par défaut :') }} <code class="font-mono">{{ config('availability.default_timezone') }}</code>
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">← {{ __('Tableau de bord') }}</a>
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <div class="rounded-2xl border bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400">{{ __('Créneaux actifs') }}</p>
                <p class="text-2xl font-black text-slate-900 dark:text-slate-100">{{ number_format($kpis['active_slots']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400">{{ __('Prestataires') }}</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['providers_total']) }}</p>
            </div>
            {{--
                L'INDICATEUR QUI MANQUAIT.

                La page comptait les prestataires « configurés » et ne pouvait pas dire qui ne
                l'était pas : sa requête n'en listait aucun. C'est pourtant le seul chiffre qui
                appelle une action.
            --}}
            <a href="#" wire:click.prevent="$set('filtre', 'sans_creneau')"
               class="rounded-2xl border p-4 shadow-sm transition {{ $kpis['providers_without_slots'] > 0 ? 'border-red-200 bg-red-50 dark:border-red-500/40 dark:bg-red-500/10' : 'bg-white dark:border-slate-700 dark:bg-slate-800' }}">
                <p class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400">{{ __('Sans disponibilité') }}</p>
                <p class="text-2xl font-black {{ $kpis['providers_without_slots'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600' }}">
                    {{ number_format($kpis['providers_without_slots']) }}
                </p>
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ __('injoignables à la planification') }}</p>
            </a>
            <div class="rounded-2xl border bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400">{{ __('Jours fermés 30j') }}</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['exceptions_30d']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400">{{ __('Réservations en cours') }}</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['active_holds']) }}</p>
            </div>
        </div>

        <div class="flex flex-col gap-2 md:flex-row">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Nom ou e-mail du prestataire…') }}"
                   class="flex-1 rounded-xl border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" />
            <select wire:model.live="filtre" class="rounded-xl border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                <option value="tous">{{ __('Tous les prestataires') }}</option>
                <option value="sans_creneau">{{ __('Sans disponibilité') }}</option>
                <option value="configures">{{ __('Avec disponibilité') }}</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-2xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 text-left">{{ __('Prestataire') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('E-mail') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Créneaux actifs') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Jours fermés 30j') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Gérer') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-slate-700">
                    @forelse($providers as $p)
                        <tr class="{{ $p->slots_count === 0 ? 'bg-red-50/60 dark:bg-red-500/5' : '' }}" wire:key="presta-{{ $p->id }}">
                            <td class="px-4 py-2 text-xs font-semibold">
                                {{-- Le nom EST le lien : c'est le geste qu'on attend d'une liste. --}}
                                <a href="{{ route('admin.availability.provider', $p) }}"
                                   class="text-indigo-600 hover:underline dark:text-indigo-400">{{ $p->name }}</a>
                                @if($p->slots_count === 0)
                                    <span class="ml-2 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-500/20 dark:text-red-300">
                                        {{ __('aucune dispo') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-slate-500 dark:text-slate-400">{{ $p->email }}</td>
                            <td class="px-4 py-2 text-right text-xs {{ $p->slots_count === 0 ? 'font-bold text-red-600 dark:text-red-400' : 'dark:text-slate-200' }}">
                                {{ $p->slots_count }}
                            </td>
                            <td class="px-4 py-2 text-right text-xs dark:text-slate-200">{{ $p->exceptions_count }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('admin.availability.provider', $p) }}"
                                   class="rounded-lg border px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                                    {{ __('Gérer') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">{{ __('Aucun prestataire ne correspond au filtre.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $providers->links() }}</div>
        </div>
    </div>
</div>
