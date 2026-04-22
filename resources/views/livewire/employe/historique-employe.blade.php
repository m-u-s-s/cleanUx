<x-page-shell
    :title="__('🕘 Historique employé')"
    :subtitle="__('Consultez vos missions terminées, vos durées réelles et les feedbacks reçus.')"
>
    
    @php
        $historyCollection = $historique->getCollection();
        $feedbackCount = $historyCollection->filter(fn ($rdv) => $rdv->feedback)->count();
        $reportCount = $historyCollection->filter(fn ($rdv) => filled($rdv->commentaire_fin_mission))->count();
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card :title="__('Missions terminées')" :value="$historique->total()" tone="slate" icon="🧾" />
        <x-kpi-card :title="__('Feedbacks reçus')" :value="$feedbackCount" tone="amber" icon="💬" />
        <x-kpi-card :title="__('Rapports saisis')" :value="$reportCount" tone="green" icon="📝" />
        <x-kpi-card :title="__('Page actuelle')" :value="$historique->count()" tone="blue" icon="📄" />
    </div>

    <x-app-card padding="p-5 md:p-6" :title="__('Filtres & recherche')" :subtitle="__('Retrouvez rapidement une mission terminée par client, service ou lieu.')">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_auto]">
            <div>
                <label class="cu-field-label">{{ __('Recherche') }}</label>
                <input type="text" wire:model.live="search" placeholder="{{ __('Client, service, ville...') }}">
            </div>
            <div class="flex items-end">
                <button wire:click="$set('tri', '{{ $tri === 'asc' ? 'desc' : 'asc' }}')" class="cu-btn-secondary">
                    {{ __('Trier : :order', ['order' => $tri === 'asc' ? __('Croissant') : __('Décroissant')]) }}
                </button>
            </div>
        </div>
    </x-app-card>

    <div class="space-y-4">
        @forelse($historique as $rdv)
            <x-app-card padding="p-5">
                <div class="cu-toolbar gap-4">
                    <div>
                        <p class="text-lg font-semibold text-slate-900">{{ $rdv->service_display_name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $rdv->date }} à {{ $rdv->heure }}</p>
                        <p class="mt-1 text-sm text-slate-600">👤 {{ $rdv->client->name ?? __('—') }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <x-badge :status="$rdv->status" />
                        <x-priority-badge :priority="$rdv->priorite" />
                    </div>
                </div>

                <div class="mt-5 cu-meta-grid text-sm text-slate-700">
                    <div class="space-y-2">
                        <p><span class="font-medium text-slate-900">{{ __('Adresse :') }}</span> {{ $rdv->location_display ?: (($rdv->adresse ?? '—') . ', ' . ($rdv->ville ?? '—')) }}</p>
                        <p><span class="font-medium text-slate-900">{{ __('Durée estimée :') }}</span> {{ $rdv->duree_estimee ? $rdv->duree_estimee . ' min' : '—' }}</p>
                        <p><span class="font-medium text-slate-900">{{ __('Durée réelle :') }}</span> {{ $rdv->duree_reelle ? $rdv->duree_reelle . ' min' : '—' }}</p>
                    </div>
                    <div class="space-y-2">
                        <p><span class="font-medium text-slate-900">{{ __('Type de lieu :') }}</span> {{ ucfirst($rdv->type_lieu ?? '—') }}</p>
                        <p><span class="font-medium text-slate-900">{{ __('Surface :') }}</span> {{ $rdv->surface ?? '—' }}</p>
                    </div>
                </div>

                @if($rdv->commentaire_fin_mission)
                    <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <span class="font-medium text-emerald-800">{{ __('Rapport de fin :') }}</span>
                        <p class="mt-1 text-emerald-900">{{ $rdv->commentaire_fin_mission }}</p>
                    </div>
                @endif

                @if($rdv->feedback)
                    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <span class="font-medium text-amber-800">{{ __('Feedback client :') }}</span>
                        <p class="mt-1">{{ __('Note :') }} {{ $rdv->feedback->note ?? '—' }}/5</p>
                        <p>{{ $rdv->feedback->commentaire ?? __('Aucun commentaire.') }}</p>
                        @if($rdv->feedback->reponse_admin)
                            <div class="mt-3 border-t border-amber-200 pt-3">
                                <span class="font-medium text-amber-800">{{ __('Réponse admin :') }}</span>
                                <p>{{ $rdv->feedback->reponse_admin }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </x-app-card>
        @empty
            <x-empty-state :title="__('Aucun historique disponible')" :message="__('Vos missions terminées apparaîtront ici.')" />
        @endforelse
    </div>

    <div class="mt-4">{{ $historique->links() }}</div>
</x-page-shell>
