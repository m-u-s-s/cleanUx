<div class="space-y-6">
    <div>
        <p class="text-sm font-semibold text-sky-700">Étape 2</p>
        <h2 class="mt-1 text-xl font-bold text-slate-900">Précisez le besoin</h2>
        <p class="mt-1 text-sm text-slate-500">Les options, zones et photos aident l’équipe à préparer la mission.</p>
    </div>

    {{-- Phase F2/F3 — champs dynamiques propres au métier choisi.
         Quand un schema est défini, on REMPLACE les sections cleaning legacy
         (options, zones, access-and-material) par le rendu du schema. --}}
    @if($this->hasTradeFormSchema())
        <div class="rounded-xl border border-blue-200 bg-blue-50/40 p-4 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-blue-900">Informations spécifiques au métier</h3>
                @php($delta = $this->tradeFormPriceDelta)
                @if(!empty($delta['breakdown']))
                    <span class="text-xs font-mono text-blue-900 bg-blue-100 rounded-full px-2 py-0.5">
                        {{ $delta['total'] > 0 ? '+' : '' }}{{ number_format($delta['total'], 2, ',', ' ') }} €
                    </span>
                @endif
            </div>
            <x-trade-form-fields :schema="$tradeFormSchema" wire-model-prefix="tradeFormAnswers" />
        </div>

        {{-- Champs universels qui restent même avec un schema --}}
        <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Matériel spécifique (optionnel)</label>
                <input type="text" wire:model.defer="materiel_specifique" class="w-full rounded-2xl border-slate-300" placeholder="Notes sur le matériel ou outils requis...">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Photos de référence</label>
                <input type="file" wire:model="photos" multiple class="w-full rounded-2xl border-slate-300">
                @error('photos.*') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
            </div>
        </div>
    @else
        @include('livewire.client.booking.details.options')
        @include('livewire.client.booking.details.zones')
        @include('livewire.client.booking.details.access-and-material')
    @endif

    @include('livewire.client.booking.details.comment')
</div>
