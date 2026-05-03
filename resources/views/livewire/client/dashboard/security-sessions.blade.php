<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                {{ __('Sécurité') }}
            </p>
            <h2 class="text-xl font-black text-slate-900">
                {{ __('Sessions actives') }}
            </h2>
        </div>

        <x-active-sessions />
    </div>
