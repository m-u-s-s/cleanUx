<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
    <div>
        <h3 class="font-semibold text-slate-900">📱 QR code mission</h3>
        <p class="text-sm text-slate-500">
            L’employé peut scanner ce QR code pour valider rapidement la mission.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        @if($startQr)
            <div class="rounded-2xl border bg-emerald-50 border-emerald-200 p-5 text-center">
                <h4 class="font-semibold text-emerald-800">Début de mission</h4>
                <p class="text-sm text-emerald-700 mb-4">À scanner quand l’employé arrive.</p>

                <img
                    src="data:image/svg+xml;base64,{{ $startQr }}"
                    class="mx-auto bg-white p-3 rounded-xl border"
                    alt="QR début mission">
            </div>
        @endif

        @if($endQr)
            <div class="rounded-2xl border bg-amber-50 border-amber-200 p-5 text-center">
                <h4 class="font-semibold text-amber-800">Fin de mission</h4>
                <p class="text-sm text-amber-700 mb-4">À scanner quand le nettoyage est terminé.</p>

                <img
                    src="data:image/svg+xml;base64,{{ $endQr }}"
                    class="mx-auto bg-white p-3 rounded-xl border"
                    alt="QR fin mission">
            </div>
        @endif
    </div>

    @if(! $startQr && ! $endQr)
        <p class="text-sm text-slate-500">
            Aucun QR code actif pour le moment.
        </p>
    @endif
</div>