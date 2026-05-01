<div class="mt-6">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <?php
            $steps = [
                1 => 'Service',
                2 => 'Détails',
                3 => 'Coordonnées',
                4 => $this->isPremiumClient() ? 'Employé & créneau' : 'Créneau',
                5 => 'Confirmation',
            ];
        ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $number => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="rounded-2xl border px-4 py-3 text-sm transition
            <?php echo e($step === $number ? 'border-sky-500 bg-sky-50 text-sky-700' : ($step > $number ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500')); ?>">
            <div class="font-semibold">Étape <?php echo e($number); ?></div>
            <div class="text-xs mt-1"><?php echo e($label); ?></div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/client/booking/stepper.blade.php ENDPATH**/ ?>