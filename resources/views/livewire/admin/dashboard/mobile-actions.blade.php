<div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 p-3 shadow-2xl backdrop-blur lg:hidden">
    <div class="grid grid-cols-4 gap-2">
        <a href="{{ route('admin.planning') }}"
           class="rounded-2xl bg-blue-600 px-2 py-3 text-center text-xs font-black text-white">
            🗓️<br>Planning
        </a>

        <a href="{{ route('admin.missions') }}"
           class="rounded-2xl bg-slate-900 px-2 py-3 text-center text-xs font-black text-white">
            📋<br>Missions
        </a>

        <a href="{{ route('admin.feedbacks') }}"
           class="rounded-2xl bg-emerald-600 px-2 py-3 text-center text-xs font-black text-white">
            💬<br>Feedbacks
        </a>

        <a href="{{ route('admin.outils') }}"
           class="rounded-2xl bg-amber-500 px-2 py-3 text-center text-xs font-black text-slate-900">
            🛠️<br>Outils
        </a>
    </div>
</div>