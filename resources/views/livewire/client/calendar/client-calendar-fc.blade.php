<div class="mx-auto max-w-7xl px-4 py-6">

    {{-- ─────── Header ─────── --}}
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Calendrier interactif</h1>
            <p class="text-sm text-slate-500">Glisse un rendez-vous pour le reprogrammer</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('client.calendar.index') }}"
               class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                Vue liste
            </a>
        </div>
    </div>

    {{-- ─────── Flash message ─────── --}}
    @if ($message)
        <div class="mb-3 flex items-start justify-between rounded-lg border px-4 py-2 text-sm
                {{ $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' }}">
            <span>{{ $messageType === 'success' ? '✅' : '❌' }} {{ $message }}</span>
            <button wire:click="clearMessage" class="ml-2 text-slate-500 hover:text-slate-700">✕</button>
        </div>
    @endif

    {{-- ─────── Filtres rapides ─────── --}}
    @if ($sites->isNotEmpty())
        <div class="mb-3 flex flex-wrap items-center gap-1.5 text-xs">
            <span class="text-slate-500">Sites :</span>
            @foreach ($sites as $site)
                <button
                    wire:click="$toggle('siteIds.{{ $site['id'] }}')"
                    type="button"
                    class="rounded-full px-2.5 py-0.5 transition
                        {{ in_array($site['id'], $siteIds) ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
                >
                    {{ $site['name'] }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- ─────── Container FullCalendar ─────── --}}
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div
            id="cleanux-fullcalendar"
            wire:ignore
            x-data="cleanuxFC()"
            x-init="init($wire)"
            class="min-h-[600px]"
        ></div>
    </div>

    {{-- ─────── Détail du booking sélectionné ─────── --}}
    @if ($this->selectedBooking)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            x-data
            @click.self="$wire.clearSelection()"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">
                            {{ $this->selectedBooking->serviceCatalog?->name ?? 'Rendez-vous' }}
                        </h3>
                        <p class="text-xs text-slate-500 font-mono">
                            {{ $this->selectedBooking->booking_reference }}
                        </p>
                    </div>
                    <button wire:click="clearSelection" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Date</dt>
                        <dd class="font-medium">
                            {{ \Carbon\Carbon::parse($this->selectedBooking->scheduled_date)->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Heure</dt>
                        <dd class="font-medium">
                            {{ $this->selectedBooking->scheduled_time
                                ? \Carbon\Carbon::parse($this->selectedBooking->scheduled_time)->format('H:i')
                                : '—' }}
                        </dd>
                    </div>
                    @if ($this->selectedBooking->organizationSite)
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Site</dt>
                            <dd class="font-medium">{{ $this->selectedBooking->organizationSite->name }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Statut</dt>
                        <dd>
                            <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium">
                                {{ $this->selectedBooking->status }}
                            </span>
                        </dd>
                    </div>
                    @if ($this->selectedBooking->address)
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Adresse</dt>
                            <dd class="text-right font-medium">
                                {{ $this->selectedBooking->address }}<br>
                                <span class="text-xs text-slate-500">
                                    {{ $this->selectedBooking->postal_code }} {{ $this->selectedBooking->city }}
                                </span>
                            </dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-4 flex gap-2">
                    @if (Route::has('client.rendezvous.index'))
                        <a href="{{ route('client.rendezvous.index') }}"
                           class="flex-1 rounded-lg bg-blue-600 px-3 py-1.5 text-center text-xs font-semibold text-white hover:bg-blue-700">
                            Voir tous mes RDV
                        </a>
                    @endif
                    <button wire:click="clearSelection"
                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
