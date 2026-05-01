<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'title',
    'value',
    'hint' => null,
    'tone' => 'slate',
    'icon' => null,
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'title',
    'value',
    'hint' => null,
    'tone' => 'slate',
    'icon' => null,
]); ?>
<?php foreach (array_filter(([
    'title',
    'value',
    'hint' => null,
    'tone' => 'slate',
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
    $toneClasses = match($tone) {
        'amber' => 'text-amber-600 bg-amber-50 border-amber-100',
        'red' => 'text-red-600 bg-red-50 border-red-100',
        'orange' => 'text-orange-600 bg-orange-50 border-orange-100',
        'rose' => 'text-rose-600 bg-rose-50 border-rose-100',
        'blue' => 'text-blue-700 bg-blue-50 border-blue-100',
        'green' => 'text-emerald-700 bg-emerald-50 border-emerald-100',
        default => 'text-slate-800 bg-slate-50 border-slate-100',
    };
?>

<div <?php echo e($attributes->merge(['class' => 'cu-kpi'])); ?>>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="cu-kpi-label"><?php echo e($title); ?></p>
            <p class="cu-kpi-value"><?php echo e($value); ?></p>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($icon): ?>
            <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border text-lg <?php echo e($toneClasses); ?>">
                <?php echo e($icon); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hint): ?>
        <p class="cu-kpi-hint"><?php echo e($hint); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/components/kpi-card.blade.php ENDPATH**/ ?>