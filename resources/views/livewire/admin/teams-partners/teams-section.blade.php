<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-900">Équipes terrain</h2>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
            {{ $teams->count() }} équipe(s)
        </span>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        @foreach($teams as $team)
            <button wire:click="selectTeam({{ $team->id }})"
                    class="rounded-2xl border p-4 text-left transition {{ $selectedTeam && $selectedTeam->id === $team->id ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-slate-50 hover:bg-white' }}">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $team->name }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ $team->serviceZone->name ?? 'Zone non définie' }}</p>
                    </div>
                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $team->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ strtoupper($team->status) }}
                    </span>
                </div>
                <div class="mt-3 text-xs text-slate-600 space-y-1">
                    <p>Lead : {{ $team->teamLead->name ?? 'Non défini' }}</p>
                    <p>Compte : {{ $team->organizationAccount->name ?? 'Générique' }}</p>
                    <p>{{ $team->is_internal ? 'Interne' : 'Partenaire' }} @if($team->servicePartner) · {{ $team->servicePartner->name }} @endif</p>
                </div>
            </button>
        @endforeach
    </div>

    @include('livewire.admin.teams-partners.team-form')
    @include('livewire.admin.teams-partners.members-panel')
</section>
