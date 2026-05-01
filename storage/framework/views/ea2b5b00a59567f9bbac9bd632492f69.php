<div class="bg-white p-4 rounded shadow space-y-4">

    <h3 class="text-xl font-semibold text-blue-800">👥 <?php echo e(__('ui.admin_users.title')); ?></h3>

    <div class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="text-sm text-gray-600"><?php echo e(__('ui.admin_users.role')); ?></label>
            <select wire:model="roleFilter" class="border rounded px-2 py-1 text-sm">
                <option value=""><?php echo e(__('ui.admin_users.all')); ?></option>
                <option value="client"><?php echo e(__('ui.admin_users.client')); ?></option>
                <option value="employe"><?php echo e(__('ui.admin_users.employee')); ?></option>
                <option value="entreprise"><?php echo e(__('ui.admin_users.company')); ?></option>
            </select>
        </div>

        <div>
            <label class="text-sm text-gray-600"><?php echo e(__('ui.admin_users.search')); ?></label>
            <input type="text" wire:model.debounce.300ms="search"
                   placeholder="<?php echo e(__('ui.admin_users.search_placeholder')); ?>"
                   class="border rounded px-2 py-1 text-sm" />
        </div>
    </div>

    <table class="w-full text-sm table-auto border mt-3">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-2 py-1 text-left"><?php echo e(__('ui.admin_users.name')); ?></th>
                <th class="px-2 py-1"><?php echo e(__('ui.admin_users.email')); ?></th>
                <th class="px-2 py-1"><?php echo e(__('ui.admin_users.role')); ?></th>
                <th class="px-2 py-1"><?php echo e(__('ui.admin_users.active')); ?></th>
                <th class="px-2 py-1"><?php echo e(__('ui.admin_users.actions')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="border-t">
                    <td class="px-2 py-1"><?php echo e($u->name); ?></td>
                    <td class="px-2 py-1"><?php echo e($u->email); ?></td>
                    <td class="px-2 py-1">
                        <select wire:change="updateRole(<?php echo e($u->id); ?>, $event.target.value)"
                                class="border px-1 text-sm">
                            <option value="client" <?php if($u->role === 'client'): echo 'selected'; endif; ?>><?php echo e(__('ui.admin_users.client')); ?></option>
                            <option value="employe" <?php if($u->role === 'employe'): echo 'selected'; endif; ?>><?php echo e(__('ui.admin_users.employee')); ?></option>
                            <option value="entreprise" <?php if($u->role === 'entreprise'): echo 'selected'; endif; ?>><?php echo e(__('ui.admin_users.company')); ?></option>
                        </select>
                    </td>
                    <td class="px-2 py-1">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($u->active): ?>
                            <span class="text-green-600 font-semibold"><?php echo e(__('ui.admin_users.yes')); ?></span>
                        <?php else: ?>
                            <span class="text-red-600 font-semibold"><?php echo e(__('ui.admin_users.no')); ?></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="px-2 py-1">
                        <button wire:click="toggleActivation(<?php echo e($u->id); ?>)"
                                class="text-xs bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">
                            <?php echo e($u->active ? __('ui.admin_users.deactivate') : __('ui.admin_users.activate')); ?>

                        </button>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>

    <div class="mt-3">
        <?php echo e($users->links()); ?>

    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/gestion-utilisateurs.blade.php ENDPATH**/ ?>