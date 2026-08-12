<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Suivi de mission</h3>
            <p class="text-sm text-slate-500">
                Statut :
                <span class="font-medium text-slate-800">{{ $mission->status }}</span>
            </p>
        </div>

        <div class="text-right text-sm text-slate-500">
            <p>Référence : <span class="font-medium text-slate-800">{{ $mission->booking?->booking_reference ?? '—' }}</span></p>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Employé principal</p>
            <p class="mt-1 font-medium text-slate-900">{{ $mission->leadEmployee?->name ?? 'Non assigné' }}</p>
        </div>

        <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Heure prévue</p>
            <p class="mt-1 font-medium text-slate-900">
                {{ optional($mission->planned_start_at)->format('d/m/Y H:i') ?? '—' }}
            </p>
        </div>
    </div>

    @if($mission->status === 'en_route')
    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
        Votre employé est en route.
    </div>
    @endif

    @if(in_array($mission->status, ['en_route', 'arrived', 'started', 'paused']))
    <livewire:client.mission-live-tracking :mission="$mission" :key="'mission-live-tracking-'.$mission->id" />
    @endif

    @if($startCodeRecord && in_array($mission->status, ['arrived']))
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 space-y-2">
        <h4 class="font-semibold text-emerald-800">Code de début disponible</h4>
        <p class="text-sm text-emerald-700">
            Donnez ce code à l’employé pour démarrer la mission.
        </p>
        <div class="inline-flex rounded-xl bg-white px-4 py-2 text-xl font-bold tracking-[0.3em] text-emerald-800">
            {{ $clientStartCode ?? 'Code en attente' }}
        </div>
    </div>
    @endif

    @if($endCodeRecord && in_array($mission->status, ['started', 'paused']))
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 space-y-2">
        <h4 class="font-semibold text-amber-800">Code de fin disponible</h4>
        <p class="text-sm text-amber-700">
            Donnez ce code à l’employé pour clôturer la mission.
        </p>
        <div class="inline-flex rounded-xl bg-white px-4 py-2 text-xl font-bold tracking-[0.3em] text-amber-800">
            {{ $clientEndCode ?? 'Code en attente' }}
        </div>
    </div>
    @endif

    @if(in_array($mission->status, ['arrived', 'started', 'paused', 'completed']))
    <livewire:client.mission-client-actions :mission="$mission" :key="'client-actions-'.$mission->id" />
    @endif

    @if($mission->status === 'completed')
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <p class="font-semibold">Merci d’avoir fait confiance à Brio.</p>
        <p class="mt-1">Votre mission est terminée.</p>
    </div>

    {{--
        L'AVIS SE DONNE ICI, PAS AILLEURS.

        Le formulaire d'avis existait déjà, sur sa propre page — atteignable seulement par un lien
        dans un courriel de fin de mission. Un avis qu'il faut aller chercher n'est pas donné : le
        client ferme la page dès qu'il a lu « mission terminée ». On le lui propose donc à l'endroit
        et à l'instant où il regarde le résultat.

        GARDE OBLIGATOIRE SUR LE PROPRIÉTAIRE. `ClientFeedbackForm::mount()` fait
        `abort_unless($rendezVous->client_id === Auth::id(), 403)`, alors que ce suivi-ci admet
        aussi les membres d'une organisation cliente. Sans cette condition, un collègue du
        titulaire ferait tomber TOUTE la page en 403 en arrivant sur une mission terminée.
    --}}
    @if($mission->booking && (int) $mission->booking->client_id === (int) auth()->id())
    <livewire:client.client-feedback-form
        :rendez-vous="$mission->booking"
        :key="'feedback-'.$mission->booking->id" />
    @endif

    <livewire:client.mission-qr-codes :mission="$mission" :key="'qr-codes-'.$mission->id" />
    <livewire:client.mission-aftercare-summary :mission="$mission" :key="'aftercare-'.$mission->id" />
    <livewire:client.mission-final-validation :mission="$mission" :key="'final-validation-'.$mission->id" />
    @endif
</div>
