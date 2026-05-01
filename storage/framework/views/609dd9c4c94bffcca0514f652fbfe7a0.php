<?php if (isset($component)) { $__componentOriginal593082f5d9bbe85e64b8909ad2db569b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['padding' => 'p-5 md:p-6','title' => __('💬 Feedbacks reçus de vos clients'),'subtitle' => __('Retrouvez vos derniers retours clients, notes et commentaires.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-5 md:p-6','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('💬 Feedbacks reçus de vos clients')),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Retrouvez vos derniers retours clients, notes et commentaires.'))]); ?>
    <div class="space-y-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $feedbacks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feedback): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="cu-list-item !bg-white">
                <?php if (isset($component)) { $__componentOriginalecb837c93e659b1cb9a06383e3284177 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalecb837c93e659b1cb9a06383e3284177 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.feedback-card','data' => ['feedback' => $feedback]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('feedback-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['feedback' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($feedback)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalecb837c93e659b1cb9a06383e3284177)): ?>
<?php $attributes = $__attributesOriginalecb837c93e659b1cb9a06383e3284177; ?>
<?php unset($__attributesOriginalecb837c93e659b1cb9a06383e3284177); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalecb837c93e659b1cb9a06383e3284177)): ?>
<?php $component = $__componentOriginalecb837c93e659b1cb9a06383e3284177; ?>
<?php unset($__componentOriginalecb837c93e659b1cb9a06383e3284177); ?>
<?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => __('Aucun feedback reçu pour le moment.'),'message' => __('Les retours de vos clients apparaîtront ici après les premières missions terminées.'),'icon' => '💬']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Aucun feedback reçu pour le moment.')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Les retours de vos clients apparaîtront ici après les premières missions terminées.')),'icon' => '💬']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $attributes = $__attributesOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__attributesOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $component = $__componentOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__componentOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="mt-4"><?php echo e($feedbacks->links()); ?></div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $attributes = $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $component = $__componentOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/feedbacks-employe.blade.php ENDPATH**/ ?>