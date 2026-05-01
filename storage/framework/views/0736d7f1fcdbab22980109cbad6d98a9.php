<div class="px-6 md:px-8 py-6 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-medium text-sky-600">
                Réservation <?php echo e($this->isPremiumClient() ? 'Premium' : 'Standard'); ?>

            </p>
            <h1 class="text-2xl md:text-3xl font-bold text-slate-900">
                Planifier une prestation
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Remplissez votre demande en quelques étapes.
            </p>
        </div>

        <?php echo $__env->make('livewire.client.booking.inline-alerts', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    </div>

    <?php echo $__env->make('livewire.client.booking.stepper', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/client/booking/header.blade.php ENDPATH**/ ?>