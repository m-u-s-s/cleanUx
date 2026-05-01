<div class="p-4 md:p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-blue-900"><?php echo e(__('👤 Mon profil')); ?></h2>
            <p class="text-sm text-gray-500">
                <?php echo e(__('Retrouvez vos informations, vos habitudes de réservation et vos raccourcis utiles.')); ?>

            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="<?php echo e(route('client.rendezvous.create')); ?>"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
                <?php echo e(__('➕ Nouveau rendez-vous')); ?>

            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl shadow border">
            <p class="text-sm text-gray-500"><?php echo e(__('Total missions')); ?></p>
            <p class="text-2xl font-bold text-slate-800"><?php echo e($stats['total']); ?></p>
        </div>

        <div class="bg-white p-4 rounded-xl shadow border">
            <p class="text-sm text-gray-500"><?php echo e(__('À venir')); ?></p>
            <p class="text-2xl font-bold text-blue-700"><?php echo e($stats['avenir']); ?></p>
        </div>

        <div class="bg-white p-4 rounded-xl shadow border">
            <p class="text-sm text-gray-500"><?php echo e(__('Terminées')); ?></p>
            <p class="text-2xl font-bold text-emerald-700"><?php echo e($stats['termine']); ?></p>
        </div>

        <div class="bg-white p-4 rounded-xl shadow border">
            <p class="text-sm text-gray-500"><?php echo e(__('Urgentes')); ?></p>
            <p class="text-2xl font-bold text-red-600"><?php echo e($stats['urgentes']); ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow border p-5">
            <h3 class="text-lg font-semibold text-slate-900 mb-4"><?php echo e(__('Informations du compte')); ?></h3>

            <div class="space-y-3 text-sm text-gray-700">
                <div>
                    <p class="text-gray-500"><?php echo e(__('Nom')); ?></p>
                    <p class="font-medium text-slate-900"><?php echo e($client->name); ?></p>
                </div>

                <div>
                    <p class="text-gray-500"><?php echo e(__('Email')); ?></p>
                    <p class="font-medium text-slate-900"><?php echo e($client->email); ?></p>
                </div>

                <div>
                    <p class="text-gray-500"><?php echo e(__('Rôle')); ?></p>
                    <p class="font-medium text-slate-900 capitalize"><?php echo e($client->role); ?></p>
                </div>

                <div class="pt-2 flex flex-wrap gap-3">
                    <a href="<?php echo e(route('profile.show')); ?>" class="text-blue-600 underline">
                        <?php echo e(__('Gérer mon compte et ma sécurité')); ?>

                    </a>

                    <a href="<?php echo e(route('client.rendezvous.create')); ?>" class="text-blue-600 underline">
                        <?php echo e(__('Nouveau rendez-vous')); ?>

                    </a>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow border p-5">
            <h3 class="text-lg font-semibold text-slate-900 mb-4"><?php echo e(__('Dernières préférences')); ?></h3>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dernierePreference): ?>
            <div class="space-y-2 text-sm text-gray-700">
                <p><span class="font-medium"><?php echo e(__('Service :')); ?></span> <?php echo e($dernierePreference->service_display_name); ?></p>
                <p><span class="font-medium"><?php echo e(__('Type de lieu :')); ?></span> <?php echo e(ucfirst($dernierePreference->type_lieu ?? '—')); ?></p>
                <p><span class="font-medium"><?php echo e(__('Fréquence :')); ?></span> <?php echo e(ucfirst(str_replace('_', ' ', $dernierePreference->frequence ?? '—'))); ?></p>
                <p><span class="font-medium"><?php echo e(__('Priorité :')); ?></span> <?php echo e(ucfirst($dernierePreference->priorite ?? '—')); ?></p>
                <p><span class="font-medium"><?php echo e(__('Créneau favori :')); ?></span> <?php echo e($dernierePreference->is_favorite_slot ? __('Oui') : __('Non')); ?></p>
            </div>
            <?php else: ?>
            <p class="text-sm text-gray-500 italic"><?php echo e(__('Aucune préférence enregistrée pour le moment.')); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow border p-5">
        <h3 class="text-lg font-semibold text-slate-900 mb-4"><?php echo e(__('Adresses récentes')); ?></h3>

        <div class="space-y-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $adressesRecentes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $adresse): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="border rounded-xl p-3 bg-gray-50">
                <p class="font-medium text-gray-800"><?php echo e($adresse->adresse); ?></p>
                <p class="text-sm text-gray-600"><?php echo e($adresse->ville ?? '—'); ?> <?php echo e($adresse->code_postal ?? ''); ?></p>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="text-sm text-gray-500 italic">
                <?php echo e(__('Aucune adresse récente.')); ?>

            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/client/profil-client.blade.php ENDPATH**/ ?>