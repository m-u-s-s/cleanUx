<div class="space-y-4">

    @if (session('success'))
        <div role="alert" class="brio-alerte brio-alerte-success">{{ session('success') }}</div>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <div>
            <label class="text-sm" for="type">Type d'import :</label>
            <select id="type" wire:model="type" class="border px-2 py-1 text-sm rounded">
                <option value="clients">👥 Clients</option>
                <option value="rendez_vous">📅 Rendez-vous</option>
            </select>
        </div>

        <div>
            <label class="text-sm" for="csv">Fichier CSV :</label>
            <input id="csv" type="file" wire:model="csv" class="text-sm" />
        </div>

        <button wire:click="import"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
            📥 Importer
        </button>
    </div>
</div>
