<div class="fixed bottom-6 right-6 z-40 hidden lg:block">
    <div class="rounded-3xl border border-slate-200 bg-white/95 p-3 shadow-2xl backdrop-blur">
        <div class="flex flex-col gap-2">
            <button wire:click="refreshDashboard"
                class="rounded-2xl bg-indigo-600 px-4 py-3 text-sm font-black text-white hover:bg-indigo-700">
                🔄 Refresh
            </button>

            <button href="<?php echo e(route('admin.planning')); ?>"
                class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-black text-white shadow-sm hover:bg-blue-700">
                🗓️ Planning
            </button>

            <button href="<?php echo e(route('admin.missions')); ?>"
                class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-black text-white shadow-sm hover:bg-slate-700">
                📋 Missions
            </button>

            <button href="<?php echo e(route('admin.feedbacks')); ?>"
                class="rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-black text-white shadow-sm hover:bg-emerald-700">
                💬 Feedbacks
            </button>

            <button href="<?php echo e(route('admin.outils')); ?>"
                class="rounded-2xl bg-amber-500 px-4 py-3 text-sm font-black text-slate-900 shadow-sm hover:bg-amber-400">
                🛠️ Outils
            </button>
        </div>
    </div>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/dashboard/quick-actions.blade.php ENDPATH**/ ?>