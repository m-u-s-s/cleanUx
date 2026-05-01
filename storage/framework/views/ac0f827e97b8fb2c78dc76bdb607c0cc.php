<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'title',
    'value',
    'hint' => null,
    'icon' => null,
    'tone' => 'slate',
    'trend' => null,
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'title',
    'value',
    'hint' => null,
    'icon' => null,
    'tone' => 'slate',
    'trend' => null,
]); ?>
<?php foreach (array_filter(([
    'title',
    'value',
    'hint' => null,
    'icon' => null,
    'tone' => 'slate',
    'trend' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $toneClasses = match($tone) {
        'amber' => 'ui-stat-icon text-amber-700 bg-amber-50 border-amber-100',
        'red' => 'ui-stat-icon text-red-700 bg-red-50 border-red-100',
        'orange' => 'ui-stat-icon text-orange-700 bg-orange-50 border-orange-100',
        'rose' => 'ui-stat-icon text-rose-700 bg-rose-50 border-rose-100',
        'blue' => 'ui-stat-icon text-blue-700 bg-blue-50 border-blue-100',
        'green' => 'ui-stat-icon text-emerald-700 bg-emerald-50 border-emerald-100',
        default => 'ui-stat-icon text-slate-700 bg-slate-50 border-slate-100',
    };
?>

<div <?php echo e($attributes->merge(['class' => 'ui-stat'])); ?>>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="ui-stat-label"><?php echo e($title); ?></p>
            <p class="ui-stat-value"><?php echo e($value); ?></p>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($icon): ?>
            <div class="<?php echo e($toneClasses); ?>">
                <?php echo e($icon); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hint || $trend): ?>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hint): ?>
                <p class="ui-stat-hint"><?php echo e($hint); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trend): ?>
                <span class="ui-badge ui-badge-neutral"><?php echo e($trend); ?></span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/components/ui/stat.blade.php ENDPATH**/ ?>