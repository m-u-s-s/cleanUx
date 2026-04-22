<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Signaler un incident</h1>
        <p class="text-sm text-gray-500">Déclare un incident terrain, ajoute des preuves et déclenche un SLA léger selon la priorité.</p>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-4 max-w-3xl">
        <div class="grid gap-4 md:grid-cols-2">
            <select wire:model="rendezVousId" class="rounded-xl border-gray-300 text-sm">
                <option value="">Mission liée (optionnel)</option>
                @foreach($this->rendezVousOptions as $rdv)
                    <option value="{{ $rdv->id }}">{{ $rdv->booking_reference }} — {{ $rdv->date?->format('d/m/Y') }} — {{ $rdv->client?->name }}</option>
                @endforeach
            </select>
            <select wire:model="type" class="rounded-xl border-gray-300 text-sm">
                <option value="incident">Incident</option>
                <option value="materiel">Matériel</option>
                <option value="securite">Sécurité</option>
                <option value="qualite">Qualité terrain</option>
            </select>
            <select wire:model="priority" class="rounded-xl border-gray-300 text-sm">
                <option value="faible">Faible</option>
                <option value="normale">Normale</option>
                <option value="haute">Haute</option>
                <option value="critique">Critique</option>
            </select>
            <input wire:model="locationNotes" type="text" class="rounded-xl border-gray-300 text-sm" placeholder="Localisation / accès / étage">
        </div>
        <input wire:model="title" type="text" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Titre de l'incident">
        <textarea wire:model="description" rows="5" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Décris précisément le problème"></textarea>
        <textarea wire:model="attachmentInput" rows="3" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Preuves / liens / chemins (1 par ligne)"></textarea>
        <div class="flex justify-end">
            <button wire:click="save" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Envoyer</button>
        </div>
    </div>
</div>
