<x-page-shell
    title="🚨 Centre d’alertes opérationnelles"
    subtitle="Surveillez les retards, missions bloquées, litiges et risques financiers.">

    <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">Alertes actives</p>
                <p class="text-4xl font-bold text-red-600">{{ $totalAlerts }}</p>
            </div>

            <div class="text-sm text-slate-500">
                Dernière mise à jour : {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <x-admin-alert-card
            title="⏰ Missions en retard"
            :items="$lateMissions"
            empty="Aucune mission en retard.">
            @foreach($lateMissions as $mission)
                <x-admin-alert-row
                    :title="$mission->rendezVous?->client?->name ?? 'Client inconnu'"
                    :subtitle="'Prévue à '.$mission->planned_start_at?->format('d/m/Y H:i')"
                    :badge="$mission->leadEmployee?->name ?? 'Employé non assigné'" />
            @endforeach
        </x-admin-alert-card>

        <x-admin-alert-card
            title="🚶 Employés non partis"
            :items="$employeesNotStarted"
            empty="Aucun départ manquant.">
            @foreach($employeesNotStarted as $mission)
                <x-admin-alert-row
                    :title="$mission->leadEmployee?->name ?? 'Employé non assigné'"
                    :subtitle="'Mission prévue à '.$mission->planned_start_at?->format('H:i')"
                    :badge="$mission->status" />
            @endforeach
        </x-admin-alert-card>

        <x-admin-alert-card
            title="🔐 Code fin non saisi"
            :items="$missingEndCode"
            empty="Aucune mission bloquée.">
            @foreach($missingEndCode as $mission)
                <x-admin-alert-row
                    :title="$mission->leadEmployee?->name ?? 'Employé inconnu'"
                    :subtitle="'Commencée à '.$mission->actual_start_at?->format('d/m/Y H:i')"
                    :badge="$mission->rendezVous?->booking_reference ?? '—'" />
            @endforeach
        </x-admin-alert-card>

        <x-admin-alert-card
            title="📡 Tracking interrompu"
            :items="$trackingInterrupted"
            empty="Aucun tracking interrompu.">
            @foreach($trackingInterrupted as $mission)
                <x-admin-alert-row
                    :title="$mission->leadEmployee?->name ?? 'Employé inconnu'"
                    :subtitle="'Dernier signal : '.$mission->activeTrackingSession?->updated_at?->format('H:i')"
                    :badge="$mission->status" />
            @endforeach
        </x-admin-alert-card>

        <x-admin-alert-card
            title="⚠️ Litiges ouverts"
            :items="$openClaims"
            empty="Aucun litige ouvert.">
            @foreach($openClaims as $claim)
                <x-admin-alert-row
                    :title="$claim->title"
                    :subtitle="$claim->client?->name ?? 'Client inconnu'"
                    :badge="$claim->status" />
            @endforeach
        </x-admin-alert-card>

        <x-admin-alert-card
            title="💸 Factures impayées"
            :items="$unpaidInvoices"
            empty="Aucune facture impayée.">
            @foreach($unpaidInvoices as $invoice)
                <x-admin-alert-row
                    :title="'Facture #'.($invoice->invoice_number ?? $invoice->id)"
                    :subtitle="$invoice->client?->name ?? 'Client inconnu'"
                    :badge="$invoice->status" />
            @endforeach
        </x-admin-alert-card>

        <x-admin-alert-card
            title="📅 RDV en attente proche"
            :items="$pendingBookings"
            empty="Aucun rendez-vous urgent en attente.">
            @foreach($pendingBookings as $rdv)
                <x-admin-alert-row
                    :title="$rdv->client?->name ?? 'Client inconnu'"
                    :subtitle="$rdv->date?->format('d/m/Y').' à '.substr((string) $rdv->heure, 0, 5)"
                    :badge="$rdv->employe?->name ?? 'Non assigné'" />
            @endforeach
        </x-admin-alert-card>

    </div>
</x-page-shell>