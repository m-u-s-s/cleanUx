{{--
    LE SIGNALEMENT DE PANNE — et ce qu'il ne fait PAS, écrit noir sur blanc.

    Laisser croire qu'il débloque produirait deux effets : des prestataires qui attendent en vain,
    et des fraudeurs qui l'essaient. On préfère décevoir tout de suite.
--}}
<x-app-card title="Le contrôle ne fonctionne pas ?"
            subtitle="Décrivez ce qui se passe. Un administrateur regardera votre dossier.">
    @if($signalementEnvoye)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-sm font-semibold text-emerald-800">Dossier ouvert.</p>
            <p class="mt-1 text-sm text-emerald-700">
                Un administrateur a été prévenu. Votre compte reste en attente de vérification :
                ce signalement ne le débloque pas.
            </p>
        </div>
    @else
        <div class="space-y-3">
            <textarea wire:model="messageDIncident"
                      rows="3"
                      placeholder="Ex. : la caméra reste noire quand j'ouvre la page."
                      class="w-full rounded-2xl border-slate-200 text-sm"></textarea>

            @error('messageDIncident') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

            <p class="text-xs text-slate-400">
                Ce signalement ne débloque pas votre compte. Il ouvre un dossier horodaté avec les
                informations techniques de votre navigateur.
            </p>

            <button type="button" wire:click="signaler" class="brio-btn-primary">Envoyer</button>
        </div>
    @endif
</x-app-card>
