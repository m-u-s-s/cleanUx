<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures()): ?>
    <div class="bg-white p-4 rounded shadow space-y-2 mt-4">
        <h3 class="text-sm font-semibold text-blue-800">🔐 Connexions actives</h3>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = Auth::user()->sessions ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $session): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="flex items-center justify-between text-sm border-b py-2">
                <div>
                    <?php echo e($session->agent['platform'] ?? 'Inconnu'); ?> -
                    <?php echo e($session->agent['browser'] ?? 'Navigateur inconnu'); ?>

                    <br>
                    <span class="text-xs text-gray-500">
                        <?php echo e($session->ip_address); ?>,
                        dernière activité : <?php echo e(\Carbon\Carbon::parse($session->last_active)->diffForHumans()); ?>

                    </span>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($session->is_current_device): ?>
                    <span class="text-green-600 text-xs font-semibold">Appareil actuel</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="text-sm text-gray-500 italic">
                Aucune session active trouvée.
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/components/active-sessions.blade.php ENDPATH**/ ?>