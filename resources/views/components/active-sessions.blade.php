@if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
    <div class="bg-white p-4 rounded shadow space-y-2 mt-4">
        <h3 class="text-sm font-semibold text-blue-800">🔐 Connexions actives</h3>

        @forelse (Auth::user()->sessions ?? [] as $session)
            <div class="flex items-center justify-between text-sm border-b py-2">
                <div>
                    {{ $session->agent['platform'] ?? 'Inconnu' }} -
                    {{ $session->agent['browser'] ?? 'Navigateur inconnu' }}
                    <br>
                    <span class="text-xs text-gray-500">
                        {{ $session->ip_address }},
                        dernière activité : {{ \Carbon\Carbon::parse($session->last_active)->diffForHumans() }}
                    </span>
                </div>

                @if ($session->is_current_device)
                    <span class="text-green-600 text-xs font-semibold">Appareil actuel</span>
                @endif
            </div>
        @empty
            <div class="text-sm text-gray-500 italic">
                Aucune session active trouvée.
            </div>
        @endforelse
    </div>
@endif