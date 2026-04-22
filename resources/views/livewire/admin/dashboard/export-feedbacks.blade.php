<div class="rounded border bg-white p-4 shadow">
    <h3 class="mb-3 text-lg font-semibold text-blue-900">📤 Exporter les feedbacks (PDF)</h3>

    <form action="{{ route('admin.feedbacks.export') }}" method="GET" target="_blank" class="space-y-3 md:flex md:items-end md:gap-4">
        <div class="flex flex-col">
            <label for="export_employe_id" class="text-sm text-gray-600">Employé :</label>
            <select name="employe_id" id="export_employe_id" class="rounded border px-2 py-1 text-sm">
                <option value="">— Tous —</option>
                @foreach($employes as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col">
            <label for="client_id" class="text-sm text-gray-600">Client :</label>
            <select name="client_id" id="client_id" class="rounded border px-2 py-1 text-sm">
                <option value="">— Tous —</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm text-white transition hover:bg-blue-700">
                📄 Télécharger le PDF
            </button>
        </div>
    </form>
</div>
