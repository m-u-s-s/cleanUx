<div class="space-y-6">
    <x-page-shell eyebrow="Qualité" title="Feedbacks clients" subtitle="Analyse des retours, réponses administratives et exports qualité.">
        <x-slot name="actions">
            <button wire:click="exportPdf" class="cu-btn-secondary">📄 Export PDF</button>
            <button wire:click="exportCsv" class="cu-btn-secondary !border-emerald-200 !bg-emerald-50 !text-emerald-700">📥 Export CSV</button>
        </x-slot>
    </x-page-shell>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-kpi-card title="Feedbacks" :value="$feedbacks->total()" tone="blue" icon="💬" />
        <x-kpi-card title="Employés" :value="$employe_id ? 1 : count($employes)" tone="amber" icon="👤" />
        <x-kpi-card title="Clients" :value="$client_id ? 1 : count($clients)" tone="green" icon="🧾" />
    </div>

    <x-filter-panel title="Filtres" subtitle="Affiner la liste avant réponse ou export.">
        <div class="cu-filter-grid">
            <div class="cu-field-stack">
                <label class="cu-field-label">Employé</label>
                <select wire:model.live="employe_id">
                    <option value="">— Tous —</option>
                    @foreach($employes as $e)
                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cu-field-stack">
                <label class="cu-field-label">Client</label>
                <select wire:model.live="client_id">
                    <option value="">— Tous —</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-panel>

    <x-app-card title="Liste des feedbacks" subtitle="Lecture détaillée, réponse admin et suivi qualité.">
        <div class="space-y-5">
            @forelse($feedbacks as $feedback)
                <div class="space-y-3 border-b border-slate-100 pb-5 last:border-b-0 last:pb-0">
                    <x-feedback-card :feedback="$feedback" />

                    <div class="cu-field-stack">
                        <label class="cu-field-label">Réponse admin</label>
                        <textarea wire:model.debounce.500ms="reponse.{{ $feedback->id }}" rows="2" class="min-h-[92px]"></textarea>
                    </div>
                </div>
            @empty
                <x-empty-state title="Aucun feedback trouvé" message="Les retours filtrés apparaîtront ici pour permettre un meilleur pilotage qualité." icon="💬" />
            @endforelse
        </div>

        <div class="mt-6">{{ $feedbacks->links() }}</div>
    </x-app-card>
</div>
