        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <x-ui.card padding="p-5" title="Capacité hebdomadaire" subtitle="Ajustez rapidement votre limite de rendez-vous par jour." eyebrow="Disponibilités">
                <div class="space-y-2">
                    @foreach(\Carbon\Carbon::now()->startOfWeek()->daysUntil(\Carbon\Carbon::now()->endOfWeek()) as $jour)
                        <div class="cu-list-item flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="text-sm font-medium text-slate-700 md:w-1/3">
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

            <x-ui.card padding="p-5" title="Accès rapides employé" subtitle="Les raccourcis les plus utiles pour piloter votre journée." eyebrow="Raccourcis">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @if(Route::has('employe.feedbacks'))
                        <x-ui.action-button :href="route('employe.feedbacks')" icon="💬">
                            Voir mes feedbacks
                        </x-ui.action-button>
                    @endif

                    @if(Route::has('employe.validation.multiple'))
                        <x-ui.action-button :href="route('employe.validation.multiple')" variant="amber" icon="✅">
                            Validation groupée
                        </x-ui.action-button>
                    @endif

                    @if(Route::has('employe.disponibilites'))
                        <x-ui.action-button :href="route('employe.disponibilites')" icon="🕒">
                            Disponibilités
                        </x-ui.action-button>
                    @endif

                    @if(Route::has('employe.coordination'))
                        <x-ui.action-button :href="route('employe.coordination')" icon="🧭">
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
