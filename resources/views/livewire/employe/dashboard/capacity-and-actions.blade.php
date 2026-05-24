<section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <x-ui.card padding="p-5" title="Capacité hebdomadaire" subtitle="Ajustez votre limite de RDV par jour." eyebrow="Disponibilités">
        <div class="space-y-2">
            @foreach(\Carbon\Carbon::now()->startOfWeek()->daysUntil(\Carbon\Carbon::now()->endOfWeek()) as $jour)
                <div class="flex flex-col gap-2 rounded-lg border border-slate-200/70 bg-white p-3 md:flex-row md:items-center md:justify-between">
                    <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 md:w-1/3">
                        <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-slate-400" />
                        {{ $jour->translatedFormat('l d F') }}
                    </div>

                    <div class="md:w-2/3">
                        @livewire('modifier-limite-jour', [
                            'date' => $jour->format('Y-m-d'),
                            'user_id' => auth()->id(),
                            'fromAdmin' => false
                        ], key($jour->format('Ymd') . '-' . auth()->id()))
                    </div>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card padding="p-5" title="Accès rapides" subtitle="Raccourcis pour piloter votre journée." eyebrow="Raccourcis">
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @if(Route::has('employe.feedbacks'))
                <x-ui.action-button :href="route('employe.feedbacks')" heroicon="chat-bubble">
                    Mes feedbacks
                </x-ui.action-button>
            @endif

            @if(Route::has('employe.validation.multiple'))
                <x-ui.action-button :href="route('employe.validation.multiple')" variant="amber" heroicon="check-circle">
                    Validation groupée
                </x-ui.action-button>
            @endif

            @if(Route::has('employe.disponibilites'))
                <x-ui.action-button :href="route('employe.disponibilites')" heroicon="clock">
                    Disponibilités
                </x-ui.action-button>
            @endif

            @if(Route::has('employe.coordination'))
                <x-ui.action-button :href="route('employe.coordination')" heroicon="users">
                    Coordination
                </x-ui.action-button>
            @endif
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4">
            <livewire:feedbacks-employe />
            <livewire:employe.feedback-stats />
            <livewire:employe.validation-multiple-rdv />
        </div>
    </x-ui.card>
</section>
