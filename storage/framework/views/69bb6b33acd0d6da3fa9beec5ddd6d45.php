<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'title' => 'Aucun résultat',
    'message' => 'Aucune donnée disponible pour le moment.',
    'icon' => '✨',
    'tone' => 'default',
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'title' => 'Aucun résultat',
    'message' => 'Aucune donnée disponible pour le moment.',
    'icon' => '✨',
    'tone' => 'default',
]); ?>
<?php foreach (array_filter(([
    'title' => 'Aucun résultat',
    'message' => 'Aucune donnée disponible pour le moment.',
    'icon' => '✨',
    'tone' => 'default',
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
        'amber' => 'ui-empty ui-empty-amber',
        default => 'ui-empty',
    };
?>

<div <?php echo e($attributes->merge(['class' => $toneClasses])); ?>>
    <div class="ui-empty-icon"><?php echo e($icon); ?></div>
    <h3 class="mt-4 text-lg font-bold text-slate-900"><?php echo e($title); ?></h3>
    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500"><?php echo e($message); ?></p>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(trim((string) $slot)): ?>
        <div class="mt-5 flex flex-wrap justify-center gap-3">
            <?php echo e($slot); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/components/ui/empty-state.blade.php ENDPATH**/ ?>