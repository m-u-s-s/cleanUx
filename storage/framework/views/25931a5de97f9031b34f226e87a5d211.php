<div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 px-6 py-7 text-white">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-100">
                    Pilotage CleanUx
                </p>

                <h1 class="mt-2 text-3xl font-black tracking-tight">
                    Tableau de bord administrateur
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-blue-100">
                    Vue consolidée des opérations, urgences, clients premium, qualité et performance terrain.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-white/15 px-3 py-1 font-semibold">
                        Scope : <?php echo e($adminScopeLabel ?? 'Global'); ?>

                    </span>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedZone ?? false): ?>
                        <span class="rounded-full bg-white/15 px-3 py-1 font-semibold">
                            Zone : <?php echo e($selectedZone->name); ?>

                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="<?php echo e(route('admin.planning')); ?>" class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-blue-700 shadow-sm hover:bg-blue-50">
                    🗓️ Planning
                </a>

                <a href="<?php echo e(route('admin.missions')); ?>" class="rounded-xl bg-white/10 px-4 py-2 text-sm font-bold text-white ring-1 ring-white/30 hover:bg-white/20">
                    📋 Missions
                </a>

                <a href="<?php echo e(route('admin.premium.clients')); ?>" class="rounded-xl bg-amber-400 px-4 py-2 text-sm font-bold text-slate-900 shadow-sm hover:bg-amber-300">
                    ★ Premium
                </a>

                <a href="<?php echo e(route('admin.outils')); ?>" class="rounded-xl bg-white/10 px-4 py-2 text-sm font-bold text-white ring-1 ring-white/30 hover:bg-white/20">
                    🛠️ Outils
                </a>
            </div>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($zoneOverview ?? false): ?>
        <div class="grid grid-cols-2 gap-0 border-t border-slate-100 bg-white md:grid-cols-4">
            <div class="border-r border-slate-100 p-4">
                <p class="text-xs font-semibold uppercase text-slate-400">Missions aujourd’hui</p>
                <p class="mt-1 text-2xl font-black text-slate-900"><?php echo e($zoneOverview['bookings_today']); ?></p>
            </div>

            <div class="border-r border-slate-100 p-4">
                <p class="text-xs font-semibold uppercase text-slate-400">Employés actifs</p>
                <p class="mt-1 text-2xl font-black text-slate-900"><?php echo e($zoneOverview['active_employees']); ?></p>
            </div>

            <div class="border-r border-slate-100 p-4">
                <p class="text-xs font-semibold uppercase text-slate-400">Clients</p>
                <p class="mt-1 text-2xl font-black text-slate-900"><?php echo e($zoneOverview['clients']); ?></p>
            </div>

            <div class="p-4">
                <p class="text-xs font-semibold uppercase text-slate-400">En attente</p>
                <p class="mt-1 text-2xl font-black text-amber-600"><?php echo e($zoneOverview['pending']); ?></p>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/dashboard/shell.blade.php ENDPATH**/ ?>