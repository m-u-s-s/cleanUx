@php
    $actions = $this->executiveActions;
@endphp

<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5">
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
            Actions recommandées
        </p>
        <h3 class="text-xl font-black text-slate-900">
            Priorités à traiter
        </h3>
        <p class="text-sm text-slate-500">
            Suggestions générées automatiquement selon les indicateurs actuels.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @forelse($actions as $action)
            <div class="rounded-2xl border p-5
                {{ $action['tone'] === 'red' ? 'border-red-200 bg-red-50' : '' }}
                {{ $action['tone'] === 'amber' ? 'border-amber-200 bg-amber-50' : '' }}
                {{ $action['tone'] === 'blue' ? 'border-blue-200 bg-blue-50' : '' }}
                {{ $action['tone'] === 'emerald' ? 'border-emerald-200 bg-emerald-50' : '' }}">

                <div class="flex items-start gap-4">
                    <div class="text-3xl">{{ $action['icon'] }}</div>

                    <div class="flex-1">
                        <h4 class="font-black text-slate-900">
                            {{ $action['title'] }}
                        </h4>

                        <p class="mt-1 text-sm text-slate-600">
                            {{ $action['message'] }}
                        </p>

                        <a href="{{ $action['route'] }}"
                           class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-700">
                            {{ $action['label'] }}
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <x-empty-state
                title="Aucune action urgente"
                message="La plateforme semble stable pour le moment."
                icon="✅"
            />
        @endforelse
    </div>
</div>