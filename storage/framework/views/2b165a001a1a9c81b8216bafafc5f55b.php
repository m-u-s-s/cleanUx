<div class="flex items-center justify-between gap-4 p-2 border rounded bg-white shadow-sm">
    <div class="flex flex-col text-sm">
        <span class="text-gray-600 font-medium">
            <?php echo e(\Carbon\Carbon::parse($date)->translatedFormat('l d F Y')); ?>

        </span>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($record?->verrou_admin): ?>
            <span class="text-red-600 text-xs font-semibold"><?php echo e(__('🔒 Verrouillé par admin')); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="flex items-center gap-2">
        <input
            type="number"
            min="0"
            wire:model.lazy="limite"
            class="w-16 text-sm border-gray-300 rounded px-2 py-1 text-center <?php echo e($record?->verrou_admin && !$fromAdmin ? 'bg-gray-100 cursor-not-allowed' : ''); ?>"
            <?php if($record?->verrou_admin && !$fromAdmin): echo 'disabled'; endif; ?>
        >
        <span class="text-xs text-gray-500"><?php echo e(__('RDV max')); ?></span>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/modifier-limite-jour.blade.php ENDPATH**/ ?>