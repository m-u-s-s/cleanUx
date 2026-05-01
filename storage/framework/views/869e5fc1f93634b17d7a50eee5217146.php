<?php
    $currentLocale = app()->getLocale();
    $languages = [
        'fr' => 'FR',
        'nl' => 'NL',
        'en' => 'EN',
    ];
?>

<div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $languages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $locale => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <form method="POST" action="<?php echo e(route('locale.switch')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="locale" value="<?php echo e($locale); ?>">
            <button type="submit"
                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition <?php echo e($currentLocale === $locale ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'); ?>">
                <?php echo e($label); ?>

            </button>
        </form>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/components/language-switcher.blade.php ENDPATH**/ ?>