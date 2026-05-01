<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'label' => null,
    'tone' => 'neutral',
    'icon' => null,
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'label' => null,
    'tone' => 'neutral',
    'icon' => null,
]); ?>
<?php foreach (array_filter(([
    'label' => null,
    'tone' => 'neutral',
    'icon' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $classes = match($tone) {
        'amber' => 'ui-badge ui-badge-amber',
        'green' => 'ui-badge ui-badge-green',
        'blue' => 'ui-badge ui-badge-blue',
        'red' => 'ui-badge ui-badge-red',
        default => 'ui-badge ui-badge-neutral',
    };
?>

<span <?php echo e($attributes->merge(['class' => $classes])); ?>>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($icon): ?>
        <span class="text-[13px] leading-none"><?php echo e($icon); ?></span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <span><?php echo e($label ?? $slot); ?></span>
</span>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/components/ui/badge.blade.php ENDPATH**/ ?>