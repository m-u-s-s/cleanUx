<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-6">
        <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Visualisation</p>
        <h3 class="text-xl font-black text-slate-900">Statistiques opérationnelles</h3>
        <p class="text-sm text-slate-500">
            Répartition des statuts et évolution mensuelle des rendez-vous.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h4 class="font-black text-slate-900">Répartition des RDV</h4>
                    <p class="text-sm text-slate-500">Confirmés, en attente et refusés.</p>
                </div>
            </div>

            <div id="chartStats" class="min-h-[300px]"></div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h4 class="font-black text-slate-900">Évolution mensuelle</h4>
                    <p class="text-sm text-slate-500">Nombre de RDV par mois.</p>
                </div>
            </div>

            <div id="chartMensuel" class="min-h-[300px]"></div>
        </div>
    </div>
</div>

<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Planning</p>
            <h3 class="text-xl font-black text-slate-900">Calendrier global</h3>
            <p class="text-sm text-slate-500">
                Vue mensuelle des rendez-vous planifiés.
            </p>
        </div>

        <a href="<?php echo e(route('admin.planning')); ?>"
           class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">
            Ouvrir le planning
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div id="fullcalendar-admin"></div>
    </div>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/dashboard/charts-and-calendar.blade.php ENDPATH**/ ?>