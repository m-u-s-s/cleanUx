<div class="p-4 md:p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-blue-900">🔗 Google Agenda</h2>
            <p class="text-sm text-gray-500">Configuration réelle de la connexion OAuth et synchronisation des missions vers Google Calendar.</p>
        </div>
        <div class="flex gap-2">
            @if($currentUserConnected)
                <form method="POST" action="{{ route('google.calendar.disconnect') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg bg-white border text-sm font-medium text-red-600">Déconnecter mon compte</button>
                </form>
            @else
                <a href="{{ route('google.calendar.connect') }}" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">Connecter mon Google Agenda</a>
            @endif
            <a href="{{ route('admin.calendar') }}" class="px-4 py-2 rounded-lg bg-white border text-sm font-medium">Retour calendrier</a>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-2xl shadow border p-5">
            <p class="text-sm text-slate-500">Connexions actives</p>
            <p class="text-3xl font-bold text-slate-900 mt-2">{{ $activeConnectionsCount }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow border p-5">
            <p class="text-sm text-slate-500">Employés connectés</p>
            <p class="text-3xl font-bold text-slate-900 mt-2">{{ $employeeConnectionsCount }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow border p-5">
            <p class="text-sm text-slate-500">Connexions en retard</p>
            <p class="text-3xl font-bold text-amber-600 mt-2">{{ $staleConnectionsCount }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow border p-5">
            <p class="text-sm text-slate-500">Connexions en erreur</p>
            <p class="text-3xl font-bold text-red-600 mt-2">{{ $errorConnectionsCount }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow border p-5">
            <p class="text-sm text-slate-500">Événements en échec</p>
            <p class="text-3xl font-bold text-slate-900 mt-2">{{ $failedEventLinksCount }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow border p-5 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="flex items-center justify-between rounded-xl border p-4">
                <div>
                    <p class="font-semibold text-slate-800">Sync agenda activée</p>
                    <p class="text-sm text-slate-500">Active le module côté plateforme.</p>
                </div>
                <input type="checkbox" wire:model="calendarSyncEnabled" class="rounded border-gray-300 text-blue-600 shadow-sm">
            </label>
            <label class="flex items-center justify-between rounded-xl border p-4">
                <div>
                    <p class="font-semibold text-slate-800">Google provider activé</p>
                    <p class="text-sm text-slate-500">Autorise la vraie connexion OAuth Google.</p>
                </div>
                <input type="checkbox" wire:model="googleCalendarEnabled" class="rounded border-gray-300 text-blue-600 shadow-sm">
            </label>
            <label class="flex items-center justify-between rounded-xl border p-4">
                <div>
                    <p class="font-semibold text-slate-800">Auto-sync employé</p>
                    <p class="text-sm text-slate-500">Permet aux employés de connecter leur agenda.</p>
                </div>
                <input type="checkbox" wire:model="googleCalendarEmployeeSelfSync" class="rounded border-gray-300 text-blue-600 shadow-sm">
            </label>
            <label class="flex items-center justify-between rounded-xl border p-4">
                <div>
                    <p class="font-semibold text-slate-800">Admin lecture seule</p>
                    <p class="text-sm text-slate-500">Garde l’admin sur un pilotage non destructif.</p>
                </div>
                <input type="checkbox" wire:model="googleCalendarAdminReadOnly" class="rounded border-gray-300 text-blue-600 shadow-sm">
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Google Client ID</label>
                <input type="text" wire:model="googleCalendarClientId" class="w-full rounded-lg border-gray-300 shadow-sm">
                @error('googleCalendarClientId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Google Client Secret</label>
                <input type="text" wire:model="googleCalendarClientSecret" class="w-full rounded-lg border-gray-300 shadow-sm">
                @error('googleCalendarClientSecret') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Redirect URI</label>
                <input type="url" wire:model="googleCalendarRedirectUri" class="w-full rounded-lg border-gray-300 shadow-sm">
                @error('googleCalendarRedirectUri') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Calendar ID par défaut</label>
                <input type="text" wire:model="defaultCalendarId" class="w-full rounded-lg border-gray-300 shadow-sm" placeholder="primary">
                @error('defaultCalendarId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Scopes</label>
                <input type="text" wire:model="googleCalendarScopes" class="w-full rounded-lg border-gray-300 shadow-sm">
                @error('googleCalendarScopes') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fenêtre passée (jours)</label>
                    <input type="number" wire:model="syncWindowPastDays" class="w-full rounded-lg border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fenêtre future (jours)</label>
                    <input type="number" wire:model="syncWindowFutureDays" class="w-full rounded-lg border-gray-300 shadow-sm">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes techniques</label>
            <textarea wire:model="notes" rows="4" class="w-full rounded-lg border-gray-300 shadow-sm"></textarea>
        </div>

        <div class="flex flex-wrap gap-3 justify-end">
            <button wire:click="save" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700">
                Enregistrer les paramètres
            </button>
            <button wire:click="syncNow" class="px-4 py-2 rounded-lg bg-slate-900 text-white font-semibold hover:bg-slate-800">
                Lancer une sync maintenant
            </button>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow border p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-900">Connexions Google récentes</h3>
            <span class="text-xs text-slate-500">20 dernières</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 pr-4">Utilisateur</th>
                        <th class="py-2 pr-4">Google email</th>
                        <th class="py-2 pr-4">Statut</th>
                        <th class="py-2 pr-4">Dernière sync</th>
                        <th class="py-2 pr-4">Erreur</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($connections as $connection)
                        <tr class="border-b last:border-0">
                            <td class="py-3 pr-4">{{ $connection->user?->name ?? '—' }}</td>
                            <td class="py-3 pr-4">{{ $connection->google_email ?? '—' }}</td>
                            <td class="py-3 pr-4">{{ $connection->last_sync_status ?? ($connection->sync_enabled ? 'active' : 'inactive') }}</td>
                            <td class="py-3 pr-4">{{ $connection->last_synced_at?->diffForHumans() ?? 'Jamais' }}</td>
                            <td class="py-3 pr-4 text-xs text-red-600">{{ $connection->last_sync_error ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-slate-500">Aucune connexion Google enregistrée.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(!empty($lastSyncSummary))
        <div class="bg-white rounded-2xl shadow border p-5">
            <h3 class="font-semibold text-slate-900 mb-4">Dernière synchronisation manuelle</h3>
            <div class="space-y-2 text-sm">
                @foreach($lastSyncSummary as $item)
                    <div class="rounded-lg border p-3">
                        <p class="font-medium text-slate-800">{{ $item['email'] }}</p>
                        <p class="text-slate-600">
                            created: {{ $item['stats']['created'] }} · updated: {{ $item['stats']['updated'] }} · deleted: {{ $item['stats']['deleted'] }} · skipped: {{ $item['stats']['skipped'] }} · errors: {{ count($item['stats']['errors']) }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
