<div class="py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Programme de parrainage</p>
                <h1 class="text-3xl font-black text-slate-900">Parrainez, gagnez des crédits</h1>
                <p class="text-sm text-slate-500 mt-2">
                    Vous gagnez {{ number_format(\App\Services\Promotion\ReferralService::DEFAULT_REFERRER_REWARD, 2, ',', ' ') }} €
                    de crédit par filleul qui complète sa première mission.
                    Le filleul reçoit {{ number_format(\App\Services\Promotion\ReferralService::DEFAULT_REFEREE_REWARD, 2, ',', ' ') }} €
                    de bienvenue.
                </p>
            </div>
        </div>

        {{-- Code + lien --}}
        <div class="rounded-3xl bg-gradient-to-br from-indigo-600 to-purple-600 p-8 text-white shadow-xl">
            <p class="text-sm font-bold uppercase opacity-80">Votre code unique</p>
            <p class="font-mono text-4xl font-black tracking-widest mt-2">{{ $this->referralCode() }}</p>
            <p class="text-xs opacity-80 mt-4">Lien d'invitation direct :</p>
            <div class="mt-2 flex flex-col md:flex-row gap-2">
                <input type="text" readonly value="{{ $inviteUrl }}"
                       class="flex-1 rounded-xl border-0 bg-white/10 text-white text-xs font-mono px-3 py-2" />
                <button wire:click="copyCode"
                        x-data
                        @click="navigator.clipboard.writeText('{{ $inviteUrl }}')"
                        class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-indigo-700 hover:bg-slate-100">
                    Copier
                </button>
            </div>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Invités</p>
                <p class="text-2xl font-black text-slate-900">{{ $stats['total_invited'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Inscrits</p>
                <p class="text-2xl font-black text-amber-600">{{ $stats['total_signed_up'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Qualifiés</p>
                <p class="text-2xl font-black text-emerald-600">{{ $stats['total_qualified'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Gains €</p>
                <p class="text-2xl font-black text-indigo-600">
                    {{ number_format((float) $stats['total_earned'], 2, ',', ' ') }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Invitation par email --}}
            <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Inviter par email</h2>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Email du filleul</label>
                    <input type="email" wire:model="inviteEmail"
                           class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    @error('inviteEmail') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">Message (optionnel)</label>
                    <textarea wire:model="inviteMessage" rows="3"
                              class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                              placeholder="J'ai testé CleanUx, je te recommande !"></textarea>
                </div>
                <button wire:click="sendInvitation"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    Envoyer l'invitation
                </button>
            </div>

            {{-- Récompenses reçues --}}
            <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Mes récompenses</h2>
                @forelse($rewards as $rw)
                    <div class="flex items-center justify-between py-2 border-b last:border-0">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">
                                {{ ucfirst($rw->reward_type) }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ optional($rw->granted_at)->format('d/m/Y') }} — {{ $rw->status }}
                            </p>
                        </div>
                        <p class="text-sm font-bold text-emerald-600">
                            +{{ number_format((float)$rw->amount, 2, ',', ' ') }} €
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-6">
                        Aucune récompense pour l'instant. Invitez vos amis !
                    </p>
                @endforelse
            </div>
        </div>

        {{-- Liste filleuls --}}
        <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-lg font-bold text-slate-900">Mes filleuls</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Filleul</th>
                            <th class="px-3 py-2 text-left">Statut</th>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-right">Récompense</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($referrals as $r)
                            <tr>
                                <td class="px-3 py-2">
                                    {{ $r->referee?->name ?? $r->referee_email ?? '—' }}
                                </td>
                                <td class="px-3 py-2">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => in_array($r->status, ['qualified','rewarded']),
                                        'bg-amber-100 text-amber-800' => in_array($r->status, ['invited','signed_up']),
                                        'bg-slate-100 text-slate-700' => in_array($r->status, ['expired','fraud_flagged']),
                                    ])>{{ $r->status }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-500">
                                    {{ optional($r->signed_up_at ?? $r->invited_at)->format('d/m/Y') }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    @if($r->isPaidOut())
                                        +{{ number_format((float)$r->referrer_reward_amount, 2, ',', ' ') }} €
                                    @else
                                        <span class="text-xs text-slate-400">en attente</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-6 text-center text-slate-400">
                                Aucun filleul pour l'instant.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
