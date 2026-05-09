@php
    $assignment = $this->assignment;
    $mission = $assignment?->mission;
    $booking = $mission?->booking;

    $isPending = $assignment && $assignment->assignment_status === 'assigned';
    $isExpired = $assignment && $assignment->expires_at && $assignment->expires_at->isPast();
    $remainingSeconds = ($assignment && $assignment->expires_at && ! $isExpired)
        ? max(0, (int) now()->diffInSeconds($assignment->expires_at, false))
        : 0;
@endphp

<div class="mx-auto max-w-md px-4 py-6">

    @if (! $assignment)
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
            <p class="text-slate-700">Cette offre est introuvable.</p>
        </div>
    @elseif (! $isPending)
        {{-- Statut final : déjà répondu --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Cette offre n'est plus active</h2>
            <p class="mt-2 text-sm text-slate-600">
                Statut : <span class="font-semibold">{{ $assignment->assignment_status }}</span>
            </p>
            @if ($assignment->assignment_status === 'accepted')
                <p class="mt-1 text-xs text-emerald-600">Vous l'avez acceptée.</p>
            @elseif (in_array($assignment->assignment_status, ['declined', 'expired', 'cancelled']))
                <p class="mt-1 text-xs text-slate-500">Elle a été proposée à un autre prestataire.</p>
            @endif
            <a href="/dashboard" class="mt-4 inline-block rounded-lg bg-slate-100 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-200">
                Retour au tableau de bord
            </a>
        </div>
    @else
        {{-- Offre en attente --}}
        <div x-data="offerCountdown({{ $remainingSeconds }})"
             x-init="init()"
             class="rounded-2xl border-2 border-blue-200 bg-white p-5 shadow-lg">

            {{-- Header avec timer --}}
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900">🚨 Nouvelle mission</h2>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500">Réponse :</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-bold"
                          :class="seconds <= 5 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'"
                          x-text="seconds + ' s'"></span>
                </div>
            </div>

            {{-- Message --}}
            @if ($message)
                <div class="mb-3 rounded-lg border px-3 py-2 text-xs
                        {{ $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' }}">
                    {{ $message }}
                </div>
            @endif

            {{-- Détails mission --}}
            <dl class="space-y-2.5 rounded-lg bg-slate-50 p-3 text-sm">
                @if ($booking?->serviceCatalog)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Service</dt>
                        <dd class="text-right font-medium">{{ $booking->serviceCatalog->name }}</dd>
                    </div>
                @endif

                @if ($booking?->booking_reference)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Référence</dt>
                        <dd class="text-right font-mono text-xs">{{ $booking->booking_reference }}</dd>
                    </div>
                @endif

                @if ($booking?->scheduled_date)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Quand</dt>
                        <dd class="text-right font-medium">
                            {{ \Carbon\Carbon::parse($booking->scheduled_date)->locale('fr')->isoFormat('ddd D MMM') }}
                            @if ($booking->scheduled_time)
                                à {{ \Carbon\Carbon::parse($booking->scheduled_time)->format('H:i') }}
                            @endif
                        </dd>
                    </div>
                @endif

                @if ($booking?->address || $booking?->city)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Adresse</dt>
                        <dd class="text-right font-medium">
                            {{ $booking->address }}<br>
                            <span class="text-xs text-slate-500">
                                {{ $booking->postal_code }} {{ $booking->city }}
                            </span>
                        </dd>
                    </div>
                @endif

                @if ($mission?->estimated_duration_minutes)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Durée estimée</dt>
                        <dd class="text-right font-medium">{{ $mission->estimated_duration_minutes }} min</dd>
                    </div>
                @endif

                @if ($mission?->client_price)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Rémunération</dt>
                        <dd class="text-right font-bold text-emerald-700">
                            {{ number_format((float) $mission->client_price, 2, ',', ' ') }} €
                        </dd>
                    </div>
                @endif

                @if ($booking?->customer_comment)
                    <div class="border-t border-slate-200 pt-2">
                        <dt class="text-slate-500 text-xs mb-1">Note du client</dt>
                        <dd class="text-xs italic text-slate-700">"{{ $booking->customer_comment }}"</dd>
                    </div>
                @endif
            </dl>

            {{-- Boutons (cachés si timer expiré) --}}
            <div x-show="seconds > 0" class="mt-4 flex gap-2">
                <button wire:click="decline"
                        x-bind:disabled="seconds <= 0"
                        class="flex-1 rounded-lg border-2 border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700">
                    ✕ Refuser
                </button>
                <button wire:click="accept"
                        x-bind:disabled="seconds <= 0"
                        class="flex-1 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-700">
                    ✓ Accepter
                </button>
            </div>

            <div x-show="seconds <= 0" x-cloak class="mt-4 rounded-lg bg-red-50 p-3 text-center text-xs text-red-700">
                ⏱ Délai dépassé. La mission est en cours de réassignation à un autre prestataire.
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    function offerCountdown(initialSeconds) {
        return {
            seconds: initialSeconds,
            timer: null,

            init() {
                if (this.seconds <= 0) return;
                this.timer = setInterval(() => {
                    this.seconds = Math.max(0, this.seconds - 1);
                    if (this.seconds <= 0) {
                        clearInterval(this.timer);
                    }
                }, 1000);
            },
        };
    }
</script>
@endpush
