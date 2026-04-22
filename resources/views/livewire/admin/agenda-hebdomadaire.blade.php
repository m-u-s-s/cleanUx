<div class="bg-white p-4 rounded shadow space-y-4">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-xl font-semibold text-blue-800">🗓️ {{ __('ui.weekly_agenda.title') }}</h3>
            <p class="text-sm text-gray-500">{{ __('ui.weekly_agenda.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <button wire:click="semainePrecedente" class="text-sm text-blue-600 hover:underline">
                ⬅️ {{ __('ui.weekly_agenda.previous_week') }}
            </button>

            <div class="text-sm font-semibold text-gray-700">
                {{ __('ui.weekly_agenda.week_of', ['date' => \Carbon\Carbon::parse($semaine)->translatedFormat('d M')]) }}
            </div>

            <button wire:click="semaineSuivante" class="text-sm text-blue-600 hover:underline">
                {{ __('ui.weekly_agenda.next_week') }} ➡️
            </button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <div>
            <label class="text-sm text-gray-700 font-medium">{{ __('ui.weekly_agenda.employee') }} :</label>
            <select wire:model.live="employe_id" class="border rounded px-2 py-1 text-sm">
                <option value="">{{ __('ui.weekly_agenda.all_employees') }}</option>
                @foreach($employes as $e)
                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-sm text-gray-700 font-medium">{{ __('ui.weekly_agenda.priority') }} :</label>
            <select wire:model.live="priorite" class="border rounded px-2 py-1 text-sm">
                <option value="">{{ __('ui.weekly_agenda.all_priorities') }}</option>
                <option value="normale">{{ __('ui.weekly_agenda.normal') }}</option>
                <option value="haute">{{ __('ui.weekly_agenda.high') }}</option>
                <option value="urgente">{{ __('ui.weekly_agenda.urgent') }}</option>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($jours as $jour)
            <div class="border rounded-xl p-4 bg-gray-50">
                <div class="mb-3">
                    <div class="font-semibold text-blue-900 text-base">
                        {{ $jour['label'] }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ __('ui.weekly_agenda.interventions_count', ['count' => $jour['rdvs']->count(), 'minutes' => $jour['total_minutes'], 'hours' => $jour['total_hours']]) }}
                    </div>
                </div>

                <div class="space-y-3">
                    @forelse($jour['rdvs'] as $rdv)
                        <x-rdv-cleaning-card :rdv="$rdv" />
                    @empty
                        <div class="text-sm text-gray-500 italic">
                            {{ __('ui.weekly_agenda.none') }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
