<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-900">Partenaires d’exécution</h2>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
            {{ $partners->count() }} partenaire(s)
        </span>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        @foreach($partners as $partner)
            <button wire:click="selectPartner({{ $partner->id }})"
                    class="rounded-2xl border p-4 text-left transition {{ $selectedPartner && $selectedPartner->id === $partner->id ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-slate-50 hover:bg-white' }}">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $partner->name }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ $partner->country->name ?? 'Pays non défini' }}</p>
                    </div>
                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $partner->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                        {{ $partner->is_active ? 'ACTIF' : 'OFF' }}
                    </span>
                </div>
                <div class="mt-3 text-xs text-slate-600 space-y-1">
                    <p>{{ $partner->email ?: 'Email non défini' }}</p>
                    <p>Qualité : {{ $partner->quality_score ?? '—' }}</p>
                </div>
            </button>
        @endforeach
    </div>

    @include('livewire.admin.teams-partners.partner-form')
    @include('livewire.admin.teams-partners.coverage-panel')
</section>
