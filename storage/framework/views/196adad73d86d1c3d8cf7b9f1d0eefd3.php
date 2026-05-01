<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'href' => null,
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'type' => 'button',
    'target' => null,
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'href' => null,
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'type' => 'button',
    'target' => null,
]); ?>
<?php foreach (array_filter(([
    'href' => null,
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'type' => 'button',
    'target' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $variantClasses = match($variant) {
        'primary' => 'ui-action ui-action-primary',
        'danger' => 'ui-action ui-action-danger',
        'amber' => 'ui-action ui-action-amber',
        'ghost' => 'ui-action ui-action-ghost',
        default => 'ui-action ui-action-secondary',
    };

    $sizeClasses = match($size) {
        'sm' => 'px-3 py-2 text-xs rounded-xl',
        'lg' => 'px-5 py-3 text-sm rounded-2xl',
        default => 'px-4 py-2.5 text-sm rounded-xl',
    };

    $classes = trim($variantClasses.' '.$sizeClasses);
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($href): ?>
    <a href="<?php echo e($href); ?>" target="<?php echo e($target); ?>" <?php echo e($attributes->merge(['class' => $classes])); ?>>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($icon): ?>
            <span class="text-base leading-none"><?php echo e($icon); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <span><?php echo e($slot); ?></span>
    </a>
<?php else: ?>
    <button type="<?php echo e($type); ?>" <?php echo e($attributes->merge(['class' => $classes])); ?>>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($icon): ?>
            <span class="text-base leading-none"><?php echo e($icon); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <span><?php echo e($slot); ?></span>
    </button>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/components/ui/action-button.blade.php ENDPATH**/ ?>