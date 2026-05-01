<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps(['status']) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps(['status']); ?>
<?php foreach (array_filter((['status']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $normalized = strtolower((string) $status);

    $config = match($normalized) {
        'confirme' => ['classes' => 'bg-green-100 text-green-700 border-green-200', 'label' => 'Confirmé'],
        'refuse' => ['classes' => 'bg-red-100 text-red-700 border-red-200', 'label' => 'Refusé'],
        'en_attente' => ['classes' => 'bg-yellow-100 text-yellow-700 border-yellow-200', 'label' => 'En attente'],
        'en_route' => ['classes' => 'bg-blue-100 text-blue-700 border-blue-200', 'label' => 'En route'],
        'sur_place' => ['classes' => 'bg-indigo-100 text-indigo-700 border-indigo-200', 'label' => 'Sur place'],
        'termine' => ['classes' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'label' => 'Terminé'],
        default => ['classes' => 'bg-gray-100 text-gray-700 border-gray-200', 'label' => ucfirst(str_replace('_', ' ', $normalized))],
    };
?>

<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold <?php echo e($config['classes']); ?>">
    <?php echo e($config['label']); ?>

</span><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/components/badge.blade.php ENDPATH**/ ?>