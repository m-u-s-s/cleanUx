<article class="space-y-4 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <h4 class="text-lg font-black text-slate-900">
                {{ $claim->title }}
            </h4>

            <p class="mt-1 text-sm text-slate-500">
                {{ $claim->category_label }}
                @if($claim->rendezVous)
                    — RDV du {{ $claim->rendezVous->date?->format('d/m/Y') }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <span class="rounded-full px-3 py-1 text-xs font-black
                    {{ $claim->status === 'resolved'
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-amber-100 text-amber-700' }}">
                {{ $claim->status_label }}
            </span>

            <span class="rounded-full px-3 py-1 text-xs font-black
                    {{ in_array($claim->priority, ['high', 'urgent'])
                        ? 'bg-red-100 text-red-700'
                        : 'bg-slate-100 text-slate-700' }}">
                {{ ucfirst($claim->priority) }}
            </span>
        </div>
    </div>

    <p class="text-sm leading-6 text-slate-700">
        {{ $claim->description }}
    </p>

    @include('livewire.client.litiges.claim-timeline', ['claim' => $claim])

    <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-slate-500">Créé le</p>
            <p class="font-bold text-slate-900">
                {{ $claim->created_at?->format('d/m/Y H:i') }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-slate-500">Réponse attendue</p>

            <p class="font-bold {{ $claim->sla_due_at && $claim->sla_due_at->isPast() && !in_array($claim->status, ['resolved', 'closed']) ? 'text-red-700' : 'text-slate-900' }}">
                {{ $claim->sla_due_at?->format('d/m/Y H:i') ?? '—' }}
            </p>

            @if($claim->sla_due_at && $claim->sla_due_at->isPast() && !in_array($claim->status, ['resolved', 'closed']))
                <p class="mt-1 text-xs font-bold text-red-600">Délai dépassé</p>
            @else
                <p class="mt-1 text-xs text-slate-500">Délai de traitement prévu</p>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-slate-500">Résolu le</p>
            <p class="font-bold text-slate-900">
                {{ $claim->resolved_at?->format('d/m/Y H:i') ?? '—' }}
            </p>
        </div>
    </div>

    @include('livewire.client.litiges.claim-attachments', ['claim' => $claim])
</article>
