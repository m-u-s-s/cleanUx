<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-kpi-card :title="__('Total prestations')" :value="$statsClient['total'] ?? 0" tone="slate" heroicon="cube" />
    <x-kpi-card :title="__('À venir')" :value="$statsClient['avenir'] ?? 0" tone="blue" heroicon="calendar" />
    <x-kpi-card :title="__('Terminées')" :value="$statsClient['termine'] ?? 0" tone="green" heroicon="check-circle" />
    <x-kpi-card :title="__('Feedbacks')" :value="$statsClient['feedbacks'] ?? 0" tone="amber" heroicon="star" />
</div>

@if(($statsClient['total'] ?? 0) === 0)
    <x-ui.empty-state
        title="{{ __('Aucune réservation pour le moment') }}"
        message="{{ __('Réservez votre premier service en 2 minutes. Nettoyage, peinture, babysitting et plus encore.') }}"
        icon="✨"
        class="mt-4">
        @if(Route::has('client.rendezvous.create'))
            <a href="{{ route('client.rendezvous.create') }}" class="brio-btn-primary inline-flex items-center gap-2">
                <x-ui.icon name="plus" class="w-4 h-4" />
                <span>{{ __('Réserver maintenant') }}</span>
            </a>
        @endif
    </x-ui.empty-state>
@endif
