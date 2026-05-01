<div class="rounded-3xl border bg-white p-6 space-y-6" wire:poll.30s="refreshAlerts">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Centre d’alertes</h2>
            <p class="text-sm text-slate-500">Surveillance opérationnelle en temps réel</p>
        </div>

        <button wire:click="refreshAlerts" class="rounded-xl bg-slate-900 px-4 py-2 text-white">
            Actualiser
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-red-50 border border-red-100 p-4">
            <p class="text-sm text-red-600">Missions en retard</p>
            <p class="text-3xl font-bold text-red-700"><?php echo e(count($alerts['late_missions'] ?? [])); ?></p>
        </div>

        <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4">
            <p class="text-sm text-amber-600">Départ bientôt</p>
            <p class="text-3xl font-bold text-amber-700"><?php echo e(count($alerts['not_started_soon'] ?? [])); ?></p>
        </div>

        <div class="rounded-2xl bg-blue-50 border border-blue-100 p-4">
            <p class="text-sm text-blue-600">Tracking coupé</p>
            <p class="text-3xl font-bold text-blue-700"><?php echo e(count($alerts['tracking_inactive'] ?? [])); ?></p>
        </div>

        <div class="rounded-2xl bg-purple-50 border border-purple-100 p-4">
            <p class="text-sm text-purple-600">Paiement à capturer</p>
            <p class="text-3xl font-bold text-purple-700"><?php echo e(count($alerts['payment_not_captured'] ?? [])); ?></p>
        </div>
    </div>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/admin-alerts-center.blade.php ENDPATH**/ ?>