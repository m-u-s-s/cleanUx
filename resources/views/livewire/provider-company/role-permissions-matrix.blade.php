<div class="min-h-screen bg-slate-900 text-slate-100 p-6">

    {{-- ── En-tête ── --}}
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Réglages de la société</p>
        <h1 class="text-2xl font-black text-white">Rôles et permissions</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-400">
            Ce que chaque rôle peut faire <strong class="text-slate-200">chez vous</strong>. Les cases
            reprennent les réglages d'usine tant que vous n'y touchez pas ; un changement s'applique
            immédiatement à toutes les personnes portant ce rôle.
        </p>
        <p class="mt-1 text-xs text-slate-500">
            Le rôle Propriétaire n'est pas réglable : il porte le droit d'ouvrir cet écran, et le lui
            retirer le fermerait à tout le monde.
        </p>
    </div>

    {{-- ── Matrice ── --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-700 bg-slate-800/60">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-700 bg-slate-800">
                <tr>
                    <th class="sticky left-0 z-10 bg-slate-800 px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">
                        Permission
                    </th>
                    @foreach ($roles as $role)
                        <th class="px-3 py-3 text-center text-[11px] font-bold text-slate-300">
                            {{ $role->label() }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-700/60">
                @foreach ($permissions as $permission)
                    <tr class="hover:bg-slate-800/40">
                        <td class="sticky left-0 z-10 bg-slate-800/60 px-4 py-2 font-mono text-xs text-slate-300">
                            {{ $permission }}
                        </td>

                        @foreach ($roles as $role)
                            @php $accorde = $matrice[$role->value][$permission] ?? false; @endphp
                            <td class="px-3 py-2 text-center">
                                <button type="button"
                                        wire:click="basculer('{{ $role->value }}', '{{ $permission }}')"
                                        wire:loading.attr="disabled"
                                        aria-pressed="{{ $accorde ? 'true' : 'false' }}"
                                        aria-label="{{ $permission }} — {{ $role->label() }}"
                                        class="h-6 w-6 rounded-md border text-xs font-black transition
                                            {{ $accorde
                                                ? 'border-emerald-500/60 bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30'
                                                : 'border-slate-600 bg-slate-900/60 text-slate-600 hover:border-slate-500' }}">
                                    {{ $accorde ? '✓' : '·' }}
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>

            <tfoot class="border-t border-slate-700 bg-slate-800/80">
                <tr>
                    <td class="sticky left-0 z-10 bg-slate-800/80 px-4 py-3 text-xs text-slate-400">
                        Revenir aux réglages d'usine
                    </td>
                    @foreach ($roles as $role)
                        <td class="px-3 py-3 text-center">
                            <button type="button"
                                    wire:click="reinitialiser('{{ $role->value }}')"
                                    class="text-[10px] font-semibold text-slate-400 underline hover:text-slate-200">
                                Réinit.
                            </button>
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>
</div>
