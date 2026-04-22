<div
    x-data="{ show: false, message: '', type: 'success', timeout: null }"
    x-show="show"
    x-transition.opacity.scale.duration.250ms
    x-init="
        Livewire.on('toast', (msg, toastType = 'success') => {
            message = msg;
            type = toastType;
            show = true;

            if (timeout) clearTimeout(timeout);

            try {
                new Audio(type === 'success' ? '/sounds/success.mp3' : '/sounds/error.mp3').play();
            } catch (e) {}

            timeout = setTimeout(() => show = false, 3600);
        });
    "
    class="fixed right-4 top-4 z-[9999] sm:right-6 sm:top-6"
    style="display: none;"
>
    <div
        class="min-w-[300px] max-w-sm overflow-hidden rounded-[22px] border px-4 py-3 text-sm font-medium shadow-[0_22px_55px_rgba(15,23,42,0.18)] backdrop-blur cu-scale-in"
        :class="{
            'bg-emerald-50/95 text-emerald-900 border-emerald-200': type === 'success',
            'bg-red-50/95 text-red-900 border-red-200': type === 'error',
            'bg-amber-50/95 text-amber-900 border-amber-200': type === 'warning',
            'bg-sky-50/95 text-sky-900 border-sky-200': type === 'info',
        }"
    >
        <div class="flex items-start gap-3">
            <div class="mt-0.5 flex h-9 w-9 items-center justify-center rounded-2xl bg-white/80 text-lg shadow-sm">
                <span x-show="type === 'success'">✅</span>
                <span x-show="type === 'error'">❌</span>
                <span x-show="type === 'warning'">⚠️</span>
                <span x-show="type === 'info'">ℹ️</span>
            </div>

            <div class="flex-1 pr-2">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-70">Notification</p>
                <div class="mt-1 leading-6" x-text="message"></div>
            </div>

            <button type="button" @click="show = false" class="text-xs font-semibold uppercase tracking-wide opacity-70 transition hover:opacity-100">
                Fermer
            </button>
        </div>

        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/70">
            <div class="h-full rounded-full"
                :class="{
                    'bg-emerald-500': type === 'success',
                    'bg-red-500': type === 'error',
                    'bg-amber-500': type === 'warning',
                    'bg-sky-500': type === 'info',
                }"
                x-bind:style="show ? 'width: 100%; transition: width 3.4s linear;' : 'width: 0%'"
            ></div>
        </div>
    </div>
</div>
