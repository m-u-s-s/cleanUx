<div class="rounded-3xl border border-blue-200 bg-white p-6 shadow-sm">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Exports</p>
            <h3 class="text-xl font-black text-slate-900">Exporter les feedbacks</h3>
            <p class="text-sm text-slate-500">
                Génère un rapport PDF filtré par employé ou client.
            </p>
        </div>

        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700 ring-1 ring-blue-200">
            PDF
        </span>
    </div>

    <form action="<?php echo e(route('admin.feedbacks.export')); ?>"
          method="GET"
          target="_blank"
          class="grid grid-cols-1 gap-4 md:grid-cols-3">

        <div>
            <label for="export_employe_id" class="mb-1 block text-sm font-bold text-slate-700">
                Employé
            </label>

            <select name="employe_id"
                    id="export_employe_id"
                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Tous les employés</option>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $employes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($emp->id); ?>"><?php echo e($emp->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </div>

        <div>
            <label for="client_id" class="mb-1 block text-sm font-bold text-slate-700">
                Client
            </label>

            <select name="client_id"
                    id="client_id"
                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Tous les clients</option>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($client->id); ?>"><?php echo e($client->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </div>

        <div class="flex items-end">
            <button type="submit"
                    class="w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-black text-white shadow-sm hover:bg-blue-700">
                📄 Télécharger le PDF
            </button>
        </div>
    </form>

    <div class="mt-5 rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
        <p class="font-bold">Astuce</p>
        <p class="mt-1">
            Laisse les filtres vides pour exporter tous les feedbacks accessibles dans ton scope admin.
        </p>
    </div>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/dashboard/export-feedbacks.blade.php ENDPATH**/ ?>