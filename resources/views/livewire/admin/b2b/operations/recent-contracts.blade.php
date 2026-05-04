        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-slate-900">Contrats récents</h2>
            <div class="mt-4 space-y-3">
                @forelse($contracts as $contract)
                    <button wire:click="loadContract({{ $contract->id }})" class="w-full rounded-2xl border border-slate-200 p-4 text-left hover:border-sky-300 hover:bg-sky-50/50">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900">{{ $contract->contract_reference }}</p>
                                <p class="text-xs text-slate-500">{{ $contract->organizationAccount?->name }} · {{ strtoupper($contract->status) }}</p>
                            </div>
                            <div class="text-right text-xs text-slate-500">
                                <div>Équipe: {{ $contract->defaultFieldTeam?->name ?? '—' }}</div>
                                <div>Partenaire: {{ $contract->defaultServicePartner?->name ?? '—' }}</div>
                            </div>
                        </div>
                    </button>
                @empty
                    <p class="text-sm text-slate-500">Aucun contrat pour ce compte.</p>
                @endforelse
            </div>
        </section>
