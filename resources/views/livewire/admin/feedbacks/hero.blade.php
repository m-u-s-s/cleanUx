<section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 text-white shadow-sm">
    <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.75fr] lg:px-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-300">
                Qualité & satisfaction
            </p>

            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                Feedbacks clients
            </h1>

            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                Analysez les retours clients, détectez les notes faibles, répondez rapidement et exportez les données qualité.
            </p>

            <div class="mt-5 flex flex-wrap gap-3">
                <button wire:click="exportPdf"
                        class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 transition hover:bg-slate-100">
                    📄 Export PDF
                </button>

                <button wire:click="exportCsv"
                        class="rounded-2xl border border-emerald-300/30 bg-emerald-400/10 px-4 py-2 text-sm font-bold text-emerald-100 transition hover:bg-emerald-400/20">
                    📥 Export CSV
                </button>

                <button wire:click="resetFilters"
                        class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                    Réinitialiser
                </button>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                Vue actuelle
            </p>

            <h2 class="mt-2 text-xl font-black text-white">
                {{ $scopeLabel }}
            </h2>

            <div class="mt-5 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-white/10 p-4">
                    <p class="text-xs text-slate-300">Satisfaction</p>
                    <p class="mt-1 text-2xl font-black text-white">
                        {{ $qualityStats['satisfaction_rate'] }}%
                    </p>
                </div>

                <div class="rounded-2xl bg-white/10 p-4">
                    <p class="text-xs text-slate-300">Moyenne</p>
                    <p class="mt-1 text-2xl font-black text-white">
                        {{ $qualityStats['average_note_label'] }}
                    </p>
                </div>
            </div>

            <p class="mt-4 text-sm text-slate-200">
                {{ $activeFiltersLabel }}
            </p>
        </div>
    </div>
</section>
