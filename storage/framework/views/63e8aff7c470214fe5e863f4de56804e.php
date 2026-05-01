<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(App\View\Components\AppLayout::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="mx-auto max-w-2xl px-4 py-8">
        <div class="rounded-3xl border bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-black text-slate-900">Laisser un feedback</h1>

            <form method="POST" action="<?php echo e(route('feedback.store', $rendezVous)); ?>" class="mt-6 space-y-4">
                <?php echo csrf_field(); ?>

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Note</label>
                    <select name="note" class="mt-1 w-full rounded-xl border-slate-300">
                        <option value="5">5 - Excellent</option>
                        <option value="4">4 - Très bien</option>
                        <option value="3">3 - Correct</option>
                        <option value="2">2 - Moyen</option>
                        <option value="1">1 - Mauvais</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Commentaire</label>
                    <textarea name="commentaire" rows="4" class="mt-1 w-full rounded-xl border-slate-300"></textarea>
                </div>

                <button class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white">
                    Envoyer
                </button>
            </form>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/feedback/create.blade.php ENDPATH**/ ?>