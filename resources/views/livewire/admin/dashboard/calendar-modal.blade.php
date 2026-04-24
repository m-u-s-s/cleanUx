<div
    x-data="{
        open: false,
        title: '',
        zone: '',
        start: '',
        init() {
            window.addEventListener('admin-calendar-event-clicked', (event) => {
                this.title = event.detail.title;
                this.zone = event.detail.zone;
                this.start = event.detail.start;
                this.open = true;
            });
        }
    }"
    x-cloak
>
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
    >
        <div
            x-show="open"
            x-transition
            @click.outside="open = false"
            class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                        Rendez-vous
                    </p>

                    <h3 class="mt-1 text-2xl font-black text-slate-900" x-text="title"></h3>

                    <p class="mt-2 text-sm text-slate-500">
                        Détails rapides depuis le calendrier global.
                    </p>
                </div>

                <button
                    type="button"
                    @click="open = false"
                    class="rounded-full bg-slate-100 px-3 py-1 text-sm font-black text-slate-600 hover:bg-slate-200"
                >
                    ✕
                </button>
            </div>

            <div class="mt-6 space-y-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">
                        Date
                    </p>
                    <p class="mt-1 font-black text-slate-900" x-text="start"></p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">
                        Zone
                    </p>
                    <p class="mt-1 font-black text-slate-900" x-text="zone"></p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    @click="open = false"
                    class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-200"
                >
                    Fermer
                </button>

                <a
                    href="{{ route('admin.planning') }}"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700"
                >
                    Ouvrir planning
                </a>
            </div>
        </div>
    </div>
    
    <div
        x-data="{ show: false, message: '', type: '' }"
        x-init="
            window.addEventListener('toast', e => {
                message = e.detail.message;
                type = e.detail.type;
                show = true;
                setTimeout(() => show = false, 3000);
            });
        "
        x-show="show"
        x-transition
        class="fixed top-6 right-6 z-50"
    >
        <div class="rounded-2xl px-4 py-3 text-sm font-bold text-white shadow-xl"
             :class="type === 'success' ? 'bg-green-600' : 'bg-blue-600'">
            <span x-text="message"></span>
        </div>
    </div>
</div>
