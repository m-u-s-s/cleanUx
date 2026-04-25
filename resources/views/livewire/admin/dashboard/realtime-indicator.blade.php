<div class="rounded-3xl border {{ $realtimeEnabled ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="relative flex h-3 w-3">
                @if($realtimeEnabled)
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                @else
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-slate-400"></span>
                @endif
            </span>

            <div>
                <p class="text-sm font-black {{ $realtimeEnabled ? 'text-emerald-800' : 'text-slate-800' }}">
                    {{ $realtimeEnabled ? 'Dashboard temps réel activé' : 'Dashboard temps réel désactivé' }}
                </p>

                <p class="text-xs {{ $realtimeEnabled ? 'text-emerald-700' : 'text-slate-500' }}">
                    @if($realtimeEnabled)
                        Mise à jour automatique toutes les 10 secondes.
                    @else
                        Les données ne se mettent plus à jour automatiquement.
                    @endif

                    @if($lastDashboardRefreshAt)
                        Dernière MAJ : {{ $lastDashboardRefreshAt }}
                    @endif
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button wire:click="toggleRealtime"
                    class="rounded-xl {{ $realtimeEnabled ? 'bg-slate-900 text-white hover:bg-slate-700' : 'bg-emerald-600 text-white hover:bg-emerald-700' }} px-3 py-2 text-xs font-black">
                {{ $realtimeEnabled ? '⏸️ Désactiver' : '▶️ Activer' }}
            </button>

            <button wire:click="refreshDashboard"
                    wire:loading.attr="disabled"
                    class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black text-white hover:bg-emerald-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="refreshDashboard">🔄 Actualiser</span>
                <span wire:loading wire:target="refreshDashboard">Mise à jour...</span>
            </button>
        </div>
    </div>
</div>