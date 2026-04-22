<div class="space-y-4">
    @if (session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
    @endif

    <x-filter-panel title="Export global" subtitle="Prépare un export rapide des données de la plateforme.">
        <div class="grid gap-4 md:grid-cols-3 md:items-end">
            <div>
                <label class="text-sm font-semibold text-slate-700">Type de données</label>
                <select wire:model="type" class="mt-1">
                    <option value="rendez_vous">📅 Rendez-vous</option>
                    <option value="utilisateurs">👥 Utilisateurs</option>
                    <option value="feedbacks">💬 Feedbacks</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-700">Format</label>
                <select wire:model="format" class="mt-1">
                    <option value="csv">📥 CSV</option>
                    <option value="pdf">📄 PDF</option>
                </select>
            </div>

            <div class="flex items-center gap-3">
                <button wire:click="export" class="cu-btn-primary w-full md:w-auto">Exporter maintenant</button>
            </div>
        </div>
    </x-filter-panel>
</div>
