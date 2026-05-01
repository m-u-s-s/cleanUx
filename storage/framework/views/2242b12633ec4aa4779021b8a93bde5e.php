<div class="space-y-4">

    <h3 class="text-lg font-semibold text-blue-900">✅ Valider plusieurs RDV en attente</h3>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="text-green-700 text-sm font-medium"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="flex gap-4">
        <button wire:click="validerSelection"
                class="bg-green-600 text-white px-4 py-1 rounded text-sm hover:bg-green-700"
                <?php if(count($selection) == 0): echo 'disabled'; endif; ?>>
            ✅ Valider (<?php echo e(count($selection)); ?>)
        </button>

        <button wire:click="refuserSelection"
                class="bg-red-600 text-white px-4 py-1 rounded text-sm hover:bg-red-700"
                <?php if(count($selection) == 0): echo 'disabled'; endif; ?>>
            ❌ Refuser
        </button>
    </div>

    <table class="w-full text-sm mt-3 table-auto border">
        <thead>
            <tr class="bg-gray-100">
                <th class="px-2 py-1">#</th>
                <th class="px-2 py-1 text-left">Date</th>
                <th class="px-2 py-1 text-left">Heure</th>
                <th class="px-2 py-1 text-left">Client</th>
                <th class="px-2 py-1">✔️</th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rdvs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="border-t">
                    <td class="px-2 py-1"><?php echo e($rdv->id); ?></td>
                    <td class="px-2 py-1"><?php echo e($rdv->date); ?></td>
                    <td class="px-2 py-1"><?php echo e($rdv->heure); ?></td>
                    <td class="px-2 py-1"><?php echo e($rdv->client->name ?? '—'); ?></td>
                    <td class="px-2 py-1 text-center">
                        <input type="checkbox"
                               wire:click="toggleSelection(<?php echo e($rdv->id); ?>)"
                               <?php if(in_array($rdv->id, $selection)): echo 'checked'; endif; ?> />
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/employe/validation-multiple-rdv.blade.php ENDPATH**/ ?>