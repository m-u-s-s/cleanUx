<div class="bg-white rounded-xl shadow border p-4">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800"><?php echo e(__('ui.notifications.title')); ?></h3>
            <p class="text-sm text-gray-500">
                <?php echo e(__('ui.notifications.unread_count', ['count' => $unreadCount])); ?>

            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?php echo e(route('notifications.index')); ?>" class="text-sm px-3 py-1 rounded border border-slate-300 text-slate-700 hover:bg-slate-50">
                <?php echo e(__('ui.notifications.view_all')); ?>

            </a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unreadCount > 0): ?>
                <button
                    wire:click="markAllAsRead"
                    class="text-sm px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">
                    <?php echo e(__('ui.notifications.mark_all_read')); ?>

                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <div class="space-y-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                $data = $notification->data ?? [];
                $message = $data['message'] ?? __('ui.notifications.item_fallback');
                $date = $notification->created_at?->diffForHumans();
                $isUnread = is_null($notification->read_at);
            ?>

            <div class="border rounded-lg p-3 <?php echo e($isUnread ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'); ?>">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800">
                            <?php echo e($message); ?>

                        </p>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($data['rdv_id'])): ?>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo e(__('ui.notifications.booking_prefix')); ?> #<?php echo e($data['rdv_id']); ?>

                            </p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <p class="text-xs text-gray-400 mt-2">
                            <?php echo e($date); ?>

                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isUnread): ?>
                            <button
                                wire:click="markAsRead('<?php echo e($notification->id); ?>')"
                                class="text-xs px-2 py-1 rounded bg-green-600 text-white hover:bg-green-700">
                                <?php echo e(__('ui.notifications.mark_read')); ?>

                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <button
                            wire:click="deleteNotification('<?php echo e($notification->id); ?>')"
                            class="text-xs px-2 py-1 rounded bg-red-600 text-white hover:bg-red-700">
                            <?php echo e(__('ui.notifications.delete')); ?>

                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="text-sm text-gray-500">
                <?php echo e(__('ui.notifications.none')); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/notifications.blade.php ENDPATH**/ ?>