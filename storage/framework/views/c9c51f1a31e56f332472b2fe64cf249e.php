<div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="mt-5">
            <label class="mb-1 block text-xs font-bold uppercase text-slate-500">
                Recherche globale
            </label>

            <div class="relative">
                <input type="text"
                    wire:model.live.debounce.400ms="dashboardSearch"
                    placeholder="Rechercher client, ville, adresse, téléphone, service..."
                    class="w-full rounded-2xl border-slate-300 py-3 pl-11 pr-4 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">

                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                    🔎
                </div>
            </div>
        </div>

        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                Filtres avancés
            </p>
            <h3 class="text-xl font-black text-slate-900">
                Affiner le dashboard
            </h3>
            <p class="text-sm text-slate-500">
                Filtre les données par employé, zone, statut ou période.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button wire:click="toggleCompactMode"
                class="rounded-xl bg-blue-50 px-4 py-2 text-sm font-black text-blue-700 ring-1 ring-blue-200 hover:bg-blue-100">
                <?php echo e($compactMode ? 'Mode détaillé' : 'Mode compact'); ?>

            </button>

            <button wire:click="resetDashboardFilters"
                wire:loading.attr="disabled"
                class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-black text-slate-700 hover:bg-slate-200 disabled:opacity-50">
                Réinitialiser
            </button>
        </div>
        <button>
            wire:loading.attr="disabled"
            class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-black text-slate-700 hover:bg-slate-200 disabled:opacity-50">
            Réinitialiser
        </button>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="mb-1 block text-xs font-bold uppercase text-slate-500">
                Employé
            </label>

            <select wire:model.live="filtreEmploye"
                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Tous les employés</option>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $employes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($emp->id); ?>"><?php echo e($emp->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-bold uppercase text-slate-500">
                Zone
            </label>

            <select wire:model.live="filtreZone"
                <?php if($zoneScopeLocked): echo 'disabled'; endif; ?>
                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-400">
                <option value="">Toutes les zones</option>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableZones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $zone): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($zone->id); ?>"><?php echo e($zone->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-bold uppercase text-slate-500">
                Statut
            </label>

            <select wire:model.live="filtreStatus"
                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Tous les statuts</option>
                <option value="en_attente">En attente</option>
                <option value="confirme">Confirmé</option>
                <option value="refuse">Refusé</option>
                <option value="en_route">En route</option>
                <option value="sur_place">Sur place</option>
                <option value="termine">Terminé</option>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-bold uppercase text-slate-500">
                Période
            </label>

            <select wire:model.live="filtrePeriode"
                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="all">Toutes les périodes</option>
                <option value="today">Aujourd’hui</option>
                <option value="week">Cette semaine</option>
                <option value="month">Ce mois</option>
            </select>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactMode || $dashboardSearch || $filtreEmploye || $filtreZone || $filtreStatus || $filtrePeriode !== 'all' || $zoneScopeLocked): ?>
    <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactMode): ?>
        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700 ring-1 ring-blue-200">
            Mode compact
        </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dashboardSearch): ?>
        <span class="rounded-full bg-purple-50 px-3 py-1 text-xs font-black text-purple-700 ring-1 ring-purple-200">
            Recherche : <?php echo e($dashboardSearch); ?>

        </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filtreEmploye): ?>
        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700 ring-1 ring-blue-200">
            Employé filtré
        </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filtreZone): ?>
        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700 ring-1 ring-indigo-200">
            Zone filtrée
        </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filtreStatus): ?>
        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-200">
            Statut : <?php echo e(ucfirst(str_replace('_', ' ', $filtreStatus))); ?>

        </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filtrePeriode !== 'all'): ?>
        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-700 ring-1 ring-amber-200">
            Période :
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php switch($filtrePeriode):
            case ('today'): ?> Aujourd’hui <?php break; ?>
            <?php case ('week'): ?> Cette semaine <?php break; ?>
            <?php case ('month'): ?> Ce mois <?php break; ?>
            <?php default: ?> Toutes
            <?php endswitch; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($zoneScopeLocked): ?>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600 ring-1 ring-slate-200">
            Scope zone verrouillé
        </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/dashboard/filters.blade.php ENDPATH**/ ?>