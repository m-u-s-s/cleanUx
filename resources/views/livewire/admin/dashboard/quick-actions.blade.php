<div class="fixed bottom-6 right-6 z-40 hidden lg:block">
    <div class="rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-soft-lg backdrop-blur">
        <div class="flex flex-col gap-1.5">
            <button wire:click="refreshDashboard" title="Rafraîchir"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition">
                <x-ui.icon name="refresh" class="w-4 h-4" />
                <span>Refresh</span>
            </button>

            <a href="{{ route('admin.planning') }}" title="Planning"
                class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 transition">
                <x-ui.icon name="calendar" class="w-4 h-4" />
                <span>Planning</span>
            </a>

            <a href="{{ route('admin.missions') }}" title="Missions"
                class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                <x-ui.icon name="briefcase" class="w-4 h-4" />
                <span>Missions</span>
            </a>

            <a href="{{ route('admin.feedbacks') }}" title="Feedbacks"
                class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                <x-ui.icon name="chat-bubble" class="w-4 h-4" />
                <span>Feedbacks</span>
            </a>

            <a href="{{ route('admin.outils') }}" title="Outils"
                class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-600 transition">
                <x-ui.icon name="wrench" class="w-4 h-4" />
                <span>Outils</span>
            </a>
        </div>
    </div>
</div>
