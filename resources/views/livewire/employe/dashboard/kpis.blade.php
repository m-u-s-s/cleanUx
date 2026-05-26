<section class="grid grid-cols-2 gap-4 xl:grid-cols-6">
    <x-ui.stat title="Total" :value="$statsJour['total']" tone="slate" heroicon="cube" hint="Missions du jour" />
    <x-ui.stat title="À faire" :value="$statsJour['a_faire']" tone="amber" heroicon="clock" hint="À démarrer" />
    <x-ui.stat title="En cours" :value="$statsJour['en_cours']" tone="blue" heroicon="truck" hint="En intervention" />
    <x-ui.stat title="Terminées" :value="$statsJour['terminees']" tone="green" heroicon="check-circle" hint="Clôturées" />
    <x-ui.stat title="Urgentes" :value="$statsJour['urgentes']" tone="red" heroicon="fire" hint="À prioriser" />
    <x-ui.stat title="Progression" :value="$statsJour['progression'] . '%'" tone="emerald" heroicon="arrow-trending-up" hint="Avancement" />
</section>

@if(($statsJour['total'] ?? 0) === 0)
    <x-ui.empty-state
        title="{{ __('Aucune mission aujourd\'hui') }}"
        message="{{ __('Passez en ligne pour recevoir des missions. Votre planning s\'affichera ici dès qu\'une mission vous sera assignée.') }}"
        icon="🟢"
        class="mt-4">
        @if(Route::has('employe.missions'))
            <a href="{{ route('employe.missions') }}" class="cu-btn-primary inline-flex items-center gap-2">
                <x-ui.icon name="arrow-right" class="w-4 h-4" />
                <span>{{ __('Voir toutes les missions') }}</span>
            </a>
        @endif
    </x-ui.empty-state>
@endif
