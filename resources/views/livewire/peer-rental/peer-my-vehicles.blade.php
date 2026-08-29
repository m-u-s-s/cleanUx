@php
    $tons = [
        'draft' => 'neutral', 'pending_review' => 'warning', 'published' => 'success',
        'paused' => 'info', 'rejected' => 'danger', 'archived' => 'neutral',
    ];
    $libelles = [
        'draft' => __('Brouillon'), 'pending_review' => __('En vérification'),
        'published' => __('En ligne'), 'paused' => __('En pause'),
        'rejected' => __('Refusée'), 'archived' => __('Archivée'),
    ];
@endphp

<div class="space-y-6">

    <x-page-shell
        :eyebrow="__('Location entre membres')"
        :title="__('Mes véhicules en location')"
        :subtitle="__('Mettez votre véhicule à disposition des autres membres. La plateforme encaisse et vous reverse.')">
        <x-slot name="actions">
            <button type="button" wire:click="creer" class="brio-btn-primary !text-xs">
                {{ __('Ajouter un véhicule') }}
            </button>
            <a href="{{ route('peer.my-rentals', ['role' => 'owner']) }}" class="brio-btn-secondary !text-xs">
                {{ __('Les demandes reçues') }}
            </a>
        </x-slot>

        @unless ($this->peutEtrePaye)
            <div class="brio-alerte brio-alerte-warning !mb-0">
                <span aria-hidden="true">💳</span>
                <span>{{ __('Terminez votre inscription au paiement : sans elle, personne ne peut vous régler.') }}</span>
                @if (\Illuminate\Support\Facades\Route::has('employe.stripe-connect.start'))
                    <a href="{{ route('employe.stripe-connect.start') }}" class="ms-auto text-xs underline">
                        {{ __('Configurer') }} →
                    </a>
                @endif
            </div>
        @endunless
    </x-page-shell>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
        <x-ui.stat :title="__('Véhicules')" :value="$this->vehicules->count()" icon="🚗" tone="blue" />
        <x-ui.stat :title="__('Revenus encaissés')"
                   :value="locale_currency($this->revenusCents / 100)" icon="💶" tone="emerald" />
        <x-ui.stat :title="__('Note')"
                   :value="$this->note ? number_format($this->note, 1, ',', ' ') . '/5' : '—'" icon="⭐" tone="amber" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->vehicules as $vehicule)
            <x-app-card class="!p-0 overflow-hidden">
                <a href="{{ route('peer.owner.vehicle', $vehicule) }}" class="block">
                    <div class="aspect-[16/10] w-full overflow-hidden bg-slate-100">
                        @if ($photo = $vehicule->photoPrincipale())
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($photo->path) }}"
                                 alt="" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <div class="flex h-full items-center justify-center text-4xl">🚗</div>
                        @endif
                    </div>

                    <div class="space-y-2 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="min-w-0 truncate text-sm font-bold text-slate-900">
                                {{ trim($vehicule->titre()) ?: __('Nouveau véhicule') }}
                            </h3>
                            <x-ui.badge :tone="$tons[$vehicule->status] ?? 'neutral'"
                                        :label="$libelles[$vehicule->status] ?? $vehicule->status"
                                        class="flex-shrink-0" />
                        </div>

                        <p class="text-xs text-slate-500">
                            {{ $vehicule->city ?: __('Adresse à compléter') }}
                            · {{ trans_choice(':count location|:count locations', $vehicule->locations_count, ['count' => $vehicule->locations_count]) }}
                        </p>

                        <p class="text-sm font-black text-slate-900">
                            <x-money :amount="$vehicule->daily_price_cents / 100" :currency="$vehicule->currency" />
                            <span class="text-xs font-medium text-slate-500">/ {{ __('jour') }}</span>
                        </p>

                        @if ($vehicule->status === 'rejected' && $vehicule->rejection_reason)
                            <p class="text-xs text-red-600">{{ $vehicule->rejection_reason }}</p>
                        @endif
                    </div>
                </a>
            </x-app-card>
        @empty
            <div class="sm:col-span-2 xl:col-span-3">
                <x-empty-state
                    icon="🚗"
                    :title="__('Aucun véhicule pour l’instant')"
                    :message="__('Ajoutez votre voiture, fixez son prix et ses disponibilités : elle apparaîtra dans la recherche dès qu’elle sera vérifiée.')">
                    <button type="button" wire:click="creer" class="brio-btn-primary !text-xs">
                        {{ __('Ajouter mon premier véhicule') }}
                    </button>
                </x-empty-state>
            </div>
        @endforelse
    </div>
</div>
