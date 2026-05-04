    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">B2B lourd</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Centre opérations entreprises</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">Contrats explicites, ordres de service, validations et affectations d’équipes/partenaires pour les comptes complexes.</p>
            </div>
            <div class="min-w-[260px]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Compte entreprise</label>
                <select wire:model.live="selectedAccountId" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Sélectionner</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->organization_contracts_count }} contrats / {{ $account->enterprise_work_orders_count }} OS)</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
