<div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-3 py-2 shadow-soft-lg backdrop-blur lg:hidden">
    <div class="grid grid-cols-4 gap-2">
        <a href="{{ route('admin.planning') }}" class="flex flex-col items-center gap-1 rounded-xl bg-slate-50 px-2 py-2 text-center text-[11px] font-semibold text-slate-700 transition active:bg-slate-100">
            <x-ui.icon name="calendar" class="w-4 h-4" />
            Planning
        </a>

        <a href="{{ route('admin.missions') }}" class="flex flex-col items-center gap-1 rounded-xl bg-slate-50 px-2 py-2 text-center text-[11px] font-semibold text-slate-700 transition active:bg-slate-100">
            <x-ui.icon name="briefcase" class="w-4 h-4" />
            Missions
        </a>

        <a href="{{ route('admin.feedbacks') }}" class="flex flex-col items-center gap-1 rounded-xl bg-slate-50 px-2 py-2 text-center text-[11px] font-semibold text-slate-700 transition active:bg-slate-100">
            <x-ui.icon name="chat-bubble" class="w-4 h-4" />
            Feedbacks
        </a>

        <a href="{{ route('admin.outils') }}" class="flex flex-col items-center gap-1 rounded-xl bg-slate-50 px-2 py-2 text-center text-[11px] font-semibold text-slate-700 transition active:bg-slate-100">
            <x-ui.icon name="wrench" class="w-4 h-4" />
            Outils
        </a>
    </div>
</div>
