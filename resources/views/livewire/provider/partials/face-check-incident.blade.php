{{--
    LE SIGNALEMENT DE PANNE — et ce qu'il ne fait PAS, écrit noir sur blanc.

    Laisser croire qu'il débloque produirait deux effets : des prestataires qui attendent en vain,
    et des fraudeurs qui l'essaient. On préfère décevoir tout de suite.
--}}
<x-app-card :title="__('face_check.incident.title')" :subtitle="__('face_check.incident.subtitle')">
    @if($signalementEnvoye)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-sm font-semibold text-emerald-800">{{ __('face_check.incident.sent_title') }}</p>
            <p class="mt-1 text-sm text-emerald-700">{{ __('face_check.incident.sent_body') }}</p>
        </div>
    @else
        <div class="space-y-3">
            <textarea wire:model="messageDIncident"
                      rows="3"
                      placeholder="{{ __('face_check.incident.placeholder') }}"
                      class="w-full rounded-2xl border-slate-200 text-sm"></textarea>

            @error('messageDIncident') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

            <p class="text-xs text-slate-400">{{ __('face_check.incident.no_unblock_warning') }}</p>

            <button type="button" wire:click="signaler" class="brio-btn-primary">{{ __('face_check.incident.send') }}</button>
        </div>
    @endif
</x-app-card>
