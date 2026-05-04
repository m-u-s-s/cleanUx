<section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 text-white shadow-sm">
    <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.8fr] lg:px-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-200">
                Automatisation opérationnelle
            </p>

            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                Automatisation missions & charge
            </h1>

            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                Mesurez la charge réelle des équipes et partenaires, puis générez les missions depuis les ordres de service approuvés et les lots planifiés.
            </p>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
            <label class="block text-xs font-semibold uppercase tracking-[0.2em] text-blue-100">
                Date de calcul
            </label>

            <input
                type="date"
                wire:model.live="selectedDate"
                class="mt-2 w-full rounded-2xl border-white/20 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm" />

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <button
                    type="button"
                    wire:click="refreshSnapshots"
                    class="rounded-2xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-black text-white transition hover:bg-white/15">
                    Recalculer la charge
                </button>

                <button
                    type="button"
                    wire:click="runPending"
                    class="rounded-2xl bg-white px-4 py-3 text-sm font-black text-slate-950 transition hover:bg-blue-50">
                    Générer les missions
                </button>
            </div>
        </div>
    </div>
</section>
