@php
    $tons = [
        'pending_owner' => 'warning', 'confirmed' => 'info', 'handed_over' => 'success',
        'returned' => 'info', 'completed' => 'success', 'disputed' => 'danger',
        'cancelled' => 'neutral', 'declined' => 'neutral', 'expired' => 'neutral',
    ];
    $libelles = [
        'pending_owner' => __('En attente'), 'confirmed' => __('Confirmée'),
        'handed_over' => __('En cours'), 'returned' => __('Rendue'),
        'completed' => __('Terminée'), 'disputed' => __('Litige'),
        'cancelled' => __('Annulée'), 'declined' => __('Refusée'), 'expired' => __('Expirée'),
    ];
@endphp

<div class="space-y-6">

    <x-page-shell
        :eyebrow="__('Location entre membres')"
        :title="$role === 'owner' ? __('Les locations de mes biens') : __('Mes locations')"
        :subtitle="__('Paiement bloqué à la réservation, capturé à la remise des clés.')">
        <x-slot name="actions">
            <a href="{{ route('peer.catalogue') }}" class="brio-btn-primary !text-xs">{{ __('Louer un véhicule') }}</a>
            <a href="{{ route('peer.sejours') }}" class="brio-btn-primary !text-xs">{{ __('Louer un logement') }}</a>
            <a href="{{ route('peer.owner.vehicles') }}" class="brio-btn-secondary !text-xs">{{ __('Mes véhicules') }}</a>
            <a href="{{ route('peer.owner.stays') }}" class="brio-btn-secondary !text-xs">{{ __('Mes logements') }}</a>
        </x-slot>
    </x-page-shell>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ([
            ['a_traiter', __('À traiter'), '📋', 'blue'],
            ['en_cours', __('En cours'), '🔑', 'emerald'],
            ['terminees', __('Terminées'), '✅', 'slate'],
            ['litiges', __('Litiges'), '⚠️', 'red'],
        ] as [$cle, $libelle, $icone, $ton])
            <x-ui.stat :title="$libelle" :value="$this->compteurs[$cle]" :icon="$icone" :tone="$ton" />
        @endforeach
    </div>

    <x-app-card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2">
                @foreach (['renter' => __('Je loue'), 'owner' => __('Je prête')] as $valeur => $libelle)
                    <button type="button" wire:click="$set('role', '{{ $valeur }}')"
                            class="{{ $role === $valeur ? 'brio-btn-primary' : 'brio-btn-secondary' }} !px-3 !py-1.5 !text-xs">
                        {{ $libelle }}
                    </button>
                @endforeach
            </div>

            <select wire:model.live="statut" class="!w-auto !py-1.5 !text-xs" aria-label="{{ __('Statut') }}">
                <option value="">{{ __('Tous les statuts') }}</option>
                @foreach ($libelles as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                @endforeach
            </select>
        </div>

        <div class="space-y-2">
            @forelse ($this->locations as $location)
                @php($bien = $location->bien())
                <a href="{{ route('peer.rental', $location) }}" class="brio-list-item flex items-center gap-3 !p-3">
                    @if ($couverture = $bien?->photoDeCouverture())
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($couverture) }}" alt=""
                             class="h-12 w-16 flex-shrink-0 rounded-lg object-cover">
                    @else
                        <div class="flex h-12 w-16 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xl">
                            {{ $bien?->typeDeBien() === 'stay' ? '🏠' : '🚗' }}
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-900">
                            {{ $bien?->titre() ?? $location->reference }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ $location->starts_at->translatedFormat('d M') }} → {{ $location->ends_at->translatedFormat('d M Y') }}
                            · {{ $role === 'owner' ? $location->renter?->name : $location->owner?->name }}
                        </p>
                    </div>

                    <span class="flex-shrink-0 text-sm font-black text-slate-900">
                        <x-money :amount="($role === 'owner' ? $location->owner_payout_cents : $location->total_cents) / 100"
                                 :currency="$location->currency" />
                    </span>

                    <x-ui.badge :tone="$tons[$location->status] ?? 'neutral'"
                                :label="$libelles[$location->status] ?? $location->status"
                                class="flex-shrink-0" />
                </a>
            @empty
                <x-empty-state
                    icon="🔑"
                    :title="$role === 'owner' ? __('Personne n’a encore loué vos biens') : __('Aucune location pour le moment')"
                    :message="$role === 'owner'
                        ? __('Publiez une annonce — véhicule ou logement — pour recevoir vos premières demandes.')
                        : __('Trouvez un véhicule ou un logement près de chez vous et réservez en deux minutes.')">
                    <a href="{{ $role === 'owner' ? route('peer.owner.vehicles') : route('peer.catalogue') }}"
                       class="brio-btn-primary !text-xs">
                        {{ $role === 'owner' ? __('Mettre un véhicule en location') : __('Voir les véhicules') }}
                    </a>
                    <a href="{{ $role === 'owner' ? route('peer.owner.stays') : route('peer.sejours') }}"
                       class="brio-btn-secondary !text-xs">
                        {{ $role === 'owner' ? __('Mettre un logement en location') : __('Voir les logements') }}
                    </a>
                </x-empty-state>
            @endforelse
        </div>

        @if ($this->locations->hasPages())
            <div class="mt-4">{{ $this->locations->links() }}</div>
        @endif
    </x-app-card>
</div>
