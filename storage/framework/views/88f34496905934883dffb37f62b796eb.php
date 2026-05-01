<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    
    
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl md:text-3xl font-bold text-slate-900"><?php echo e(__('Mes employés favoris')); ?></h1>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPremium): ?>
                <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">
                    <?php echo e(__('★ Premium')); ?>

                </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <p class="text-sm text-slate-500 mt-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPremium): ?>
                <?php echo e(__('Sélectionnez vos employés favoris pour retrouver une expérience plus personnalisée.')); ?>

                <?php else: ?>
                <?php echo e(__('Cette fonctionnalité est disponible avec l’offre Premium mensuelle.')); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="<?php echo e(route('client.dashboard')); ?>"
                class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                <?php echo e(__('Retour au dashboard')); ?>

            </a>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isPremium): ?>
            <a href="<?php echo e(route('premium.offer')); ?>"
                class="inline-flex items-center justify-center rounded-2xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 transition">
                <?php echo e(__('Découvrir Premium')); ?>

            </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isPremium): ?>
    <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
        <p class="text-sm font-semibold text-amber-800"><?php echo e(__('Fonctionnalité Premium')); ?></p>
        <h2 class="mt-2 text-xl font-bold text-slate-900"><?php echo e(__('Choisissez vos employés favoris')); ?></h2>
        <p class="mt-3 text-sm text-slate-700 max-w-2xl">
            <?php echo e(__('Avec l’offre Premium mensuelle, vous pouvez sélectionner vos employés favoris, consulter leurs disponibilités et profiter d’une expérience plus personnalisée.')); ?>

        </p>

        <div class="mt-5">
            <a href="<?php echo e(route('premium.offer')); ?>"
                class="inline-flex items-center justify-center rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-white hover:bg-amber-600 transition">
                <?php echo e(__('Passer en Premium')); ?>

            </a>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
        <div class="max-w-md">
            <label class="block text-sm font-semibold text-slate-700 mb-2"><?php echo e(__('Rechercher un employé')); ?></label>
            <input type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="<?php echo e(__('Nom de l’employé...')); ?>"
                class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
        </div>
    </div>

    
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $employes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employe): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
        $isFavorite = in_array($employe->id, $favoriteIds);
        ?>

        <div class="bg-white rounded-3xl border <?php echo e($isFavorite ? 'border-amber-200' : 'border-slate-200'); ?> shadow-sm p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-lg font-bold text-slate-900"><?php echo e($employe->name); ?></h3>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFavorite): ?>
                        <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2.5 py-1 text-xs font-semibold text-amber-700">
                            <?php echo e(__('Favori')); ?>

                        </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <p class="text-sm text-slate-500 mt-2">
                        <?php echo e(__('Employé de votre équipe')); ?>

                    </p>
                </div>
            </div>

            <div class="mt-5 flex items-center gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPremium): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFavorite): ?>
                <button type="button"
                    wire:click="removeFavorite(<?php echo e($employe->id); ?>)"
                    class="inline-flex items-center justify-center rounded-2xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-100 transition">
                    <?php echo e(__('Retirer des favoris')); ?>

                </button>
                <?php else: ?>
                <button type="button"
                    wire:click="addFavorite(<?php echo e($employe->id); ?>)"
                    class="inline-flex items-center justify-center rounded-2xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 transition">
                    <?php echo e(__('Ajouter aux favoris')); ?>

                </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <a href="<?php echo e(route('client.rendezvous.create', ['employe' => $employe->id])); ?>"
                    class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    <?php echo e(__('Réserver')); ?>

                </a>
                <?php else: ?>
                <a href="<?php echo e(route('premium.offer')); ?>"
                    class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 transition">
                    <?php echo e(__('Débloquer avec Premium')); ?>

                </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="md:col-span-2 xl:col-span-3">
            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                <p class="text-slate-700 font-medium"><?php echo e(__('Aucun employé trouvé.')); ?></p>
                <p class="text-sm text-slate-500 mt-1"><?php echo e(__('Essayez une autre recherche.')); ?></p>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/client/favorite-employes-manager.blade.php ENDPATH**/ ?>