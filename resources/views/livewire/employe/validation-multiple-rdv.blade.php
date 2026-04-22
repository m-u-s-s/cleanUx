<div class="space-y-4">

    <h3 class="text-lg font-semibold text-blue-900">✅ Valider plusieurs RDV en attente</h3>

    @if (session('success'))
        <div class="text-green-700 text-sm font-medium">{{ session('success') }}</div>
    @endif

    <div class="flex gap-4">
        <button wire:click="validerSelection"
                class="bg-green-600 text-white px-4 py-1 rounded text-sm hover:bg-green-700"
                @disabled(count($selection) == 0)>
            ✅ Valider ({{ count($selection) }})
        </button>

        <button wire:click="refuserSelection"
                class="bg-red-600 text-white px-4 py-1 rounded text-sm hover:bg-red-700"
                @disabled(count($selection) == 0)>
            ❌ Refuser
        </button>
    </div>

    <table class="w-full text-sm mt-3 table-auto border">
        <thead>
            <tr class="bg-gray-100">
                <th class="px-2 py-1">#</th>
                <th class="px-2 py-1 text-left">Date</th>
                <th class="px-2 py-1 text-left">Heure</th>
                <th class="px-2 py-1 text-left">Client</th>
                <th class="px-2 py-1">✔️</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rdvs as $rdv)
                <tr class="border-t">
                    <td class="px-2 py-1">{{ $rdv->id }}</td>
                    <td class="px-2 py-1">{{ $rdv->date }}</td>
                    <td class="px-2 py-1">{{ $rdv->heure }}</td>
                    <td class="px-2 py-1">{{ $rdv->client->name ?? '—' }}</td>
                    <td class="px-2 py-1 text-center">
                        <input type="checkbox"
                               wire:click="toggleSelection({{ $rdv->id }})"
                               @checked(in_array($rdv->id, $selection)) />
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
