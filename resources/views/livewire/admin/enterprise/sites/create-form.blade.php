    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <h3 class="font-semibold text-slate-900">➕ Ajouter un site</h3>

        <div class="grid grid-cols-2 gap-3 mt-4">
            <input wire:model="name" placeholder="Nom" class="rounded-xl border" />
            <input wire:model="address" placeholder="Adresse" class="rounded-xl border" />
            <input wire:model="city" placeholder="Ville" class="rounded-xl border" />
            <input wire:model="cost_center" placeholder="Centre de coût" class="rounded-xl border" />
        </div>

        <button wire:click="create"
            class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-xl">
            Ajouter
        </button>
    </div>
