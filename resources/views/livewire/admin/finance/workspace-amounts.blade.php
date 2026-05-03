<div class="rounded-2xl border border-slate-200 p-3">
    <div class="text-xs uppercase tracking-wide text-slate-400">Montants</div>
    <div class="mt-2 space-y-1">
        <div>HTVA : <span class="font-semibold text-slate-800">€ {{ number_format($this->amountHtva($selectedRendezVous), 2, ',', ' ') }}</span></div>
        <div>TVA : <span class="font-semibold text-slate-800">€ {{ number_format($this->amountTva($selectedRendezVous), 2, ',', ' ') }}</span></div>
        <div>TVAC : <span class="font-semibold text-slate-800">€ {{ number_format($this->amountTvac($selectedRendezVous), 2, ',', ' ') }}</span></div>
        <div>Marge estimée : <span class="font-semibold text-slate-800">€ {{ number_format($this->marginEstimate($selectedRendezVous), 2, ',', ' ') }}</span></div>
    </div>
</div>
