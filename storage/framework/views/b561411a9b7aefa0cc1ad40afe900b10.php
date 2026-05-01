<div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4">
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
            Personnalisation
        </p>
        <h3 class="text-xl font-black text-slate-900">
            Sections visibles
        </h3>
        <p class="text-sm text-slate-500">
            Active ou masque les blocs du dashboard selon ton besoin.
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
        'operations' => 'Opérations',
        'analytics' => 'Analyse',
        'premium' => 'Premium',
        'charts' => 'Graphiques',
        'tools' => 'Outils',
        'modules' => 'Modules'
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <button type="button"
            wire:click="toggleDashboardSection('<?php echo e($key); ?>')"
            class="rounded-xl px-4 py-2 text-sm font-black transition
                    <?php echo e($visibleDashboardSections[$key] ?? false
                        ? 'bg-blue-600 text-white shadow-sm hover:bg-blue-700'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'); ?>">
            <?php echo e(($visibleDashboardSections[$key] ?? false) ? '✓' : '+'); ?> <?php echo e($label); ?>

        </button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="mt-4 border-t border-slate-100 pt-4">
        <button wire:click="resetDashboardPreferences"
            class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-black text-slate-700 hover:bg-slate-200">
            Réinitialiser préférences
        </button>
        <button wire:click="toggleExecutiveMode"
            class="rounded-xl px-4 py-2 text-sm font-black transition
        <?php echo e($executiveMode
            ? 'bg-slate-900 text-white shadow-sm hover:bg-slate-700'
            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'); ?>">
            <?php echo e($executiveMode ? '✓' : '+'); ?> Mode exécutif
        </button>
    </div>

    <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
        <p class="text-sm font-black text-emerald-800">
            Préférences dashboard actives
        </p>
        <p class="mt-1 text-xs text-emerald-700">
            Les sections visibles, le mode compact et le temps réel sont sauvegardés dans la session.
        </p>
    </div>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/dashboard/section-controls.blade.php ENDPATH**/ ?>