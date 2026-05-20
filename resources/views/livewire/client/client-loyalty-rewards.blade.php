<div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Programme fidélité</p>
            <h1 class="text-2xl font-black text-slate-900">Mes points & récompenses</h1>
        </div>
        <div class="rounded-2xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-4 shadow-lg">
            <p class="text-xs font-semibold opacity-80">Solde de points</p>
            <p class="text-3xl font-black">{{ number_format($balance) }} pts</p>
            <p class="text-xs opacity-80 mt-1">Niveau : {{ $tierName }}</p>
        </div>
    </div>

    <div class="flex gap-2 border-b mb-4">
        <button wire:click="setTab('catalogue')" class="px-4 py-2 text-sm font-semibold {{ $tab === 'catalogue' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500' }}">
            Catalogue
        </button>
        <button wire:click="setTab('mes-redemptions')" class="px-4 py-2 text-sm font-semibold {{ $tab === 'mes-redemptions' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500' }}">
            Mes rédemptions
        </button>
    </div>

    @if ($tab === 'catalogue')
        <div class="mb-4">
            <select wire:model.live="typeFilter" class="rounded-lg border-slate-300 text-sm">
                <option value="">Tous types</option>
                <option value="discount_code">Code réduction</option>
                <option value="service_credit">Crédit service</option>
                <option value="physical_item">Objet physique</option>
                <option value="partner_voucher">Voucher partenaire</option>
                <option value="charity_donation">Don caritatif</option>
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($rewards as $r)
                @php $canAfford = $balance >= $r->points_cost; @endphp
                <div class="rounded-2xl border bg-white shadow-sm overflow-hidden hover:shadow-md transition">
                    @if ($r->image_url)
                        <img src="{{ $r->image_url }}" alt="{{ $r->name }}" class="w-full h-32 object-cover">
                    @else
                        <div class="w-full h-32 bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center">
                            <span class="text-5xl">🎁</span>
                        </div>
                    @endif
                    <div class="p-4">
                        <p class="text-xs uppercase font-bold text-indigo-600">{{ $r->category ?? $r->reward_type }}</p>
                        <h3 class="font-bold text-slate-900 mt-1">{{ $r->name }}</h3>
                        @if ($r->description)
                            <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $r->description }}</p>
                        @endif
                        <div class="flex items-center justify-between mt-3">
                            <div>
                                <p class="text-lg font-black text-indigo-700">{{ number_format($r->points_cost) }} pts</p>
                                @if ($r->valueFormatted())
                                    <p class="text-xs text-slate-500">≈ {{ $r->valueFormatted() }}</p>
                                @endif
                            </div>
                            @if ($r->min_tier_level > 0)
                                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">
                                    {{ ['Bronze','Silver','Gold','Platinum'][$r->min_tier_level] }}+
                                </span>
                            @endif
                        </div>
                        @if ($r->stock_remaining !== null && $r->stock_remaining < 10)
                            <p class="text-xs text-rose-600 font-semibold mt-2">Plus que {{ $r->stock_remaining }} en stock</p>
                        @endif
                        <button wire:click="openReward({{ $r->id }})" @disabled(!$canAfford)
                                class="w-full mt-3 rounded-lg {{ $canAfford ? 'bg-indigo-600 hover:bg-indigo-500' : 'bg-slate-300 cursor-not-allowed' }} text-white py-2 text-sm font-semibold">
                            {{ $canAfford ? 'Échanger' : 'Pts insuffisants' }}
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-3 py-12 text-center text-slate-400">Aucune récompense disponible pour le moment.</div>
            @endforelse
        </div>
        <div class="mt-4">{{ $rewards->links() }}</div>
    @endif

    @if ($tab === 'mes-redemptions')
        <div class="rounded-2xl border bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Récompense</th>
                        <th class="px-3 py-2">Points</th>
                        <th class="px-3 py-2">Voucher</th>
                        <th class="px-3 py-2">Statut</th>
                        <th class="px-3 py-2">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($myRedemptions as $d)
                        <tr class="border-t">
                            <td class="px-3 py-2 font-mono text-xs">{{ $d->code }}</td>
                            <td class="px-3 py-2 font-semibold">{{ $d->reward?->name }}</td>
                            <td class="px-3 py-2 text-indigo-700 font-bold">{{ number_format($d->points_spent) }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $d->voucher_code ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @php $color = ['pending'=>'amber','confirmed'=>'indigo','delivered'=>'emerald','cancelled'=>'rose','refunded'=>'slate'][$d->status] ?? 'slate'; @endphp
                                <span class="inline-block rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 text-xs font-semibold">{{ $d->status }}</span>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $d->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Aucune rédemption pour le moment.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $myRedemptions->links() }}</div>
        </div>
    @endif

    {{-- Modal confirmation rédemption --}}
    @if ($selectedReward)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                <div class="flex justify-between items-start mb-4">
                    <h2 class="text-lg font-bold text-slate-900">Confirmer l'échange</h2>
                    <button wire:click="closeReward" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">×</button>
                </div>
                <div class="bg-indigo-50 rounded-lg p-4 mb-4">
                    <p class="text-sm font-semibold text-slate-700">{{ $selectedReward->name }}</p>
                    @if ($selectedReward->description)
                        <p class="text-xs text-slate-500 mt-1">{{ $selectedReward->description }}</p>
                    @endif
                    <div class="flex justify-between mt-3">
                        <span class="text-xs text-slate-500">Coût</span>
                        <span class="text-sm font-bold text-indigo-700">{{ number_format($selectedReward->points_cost) }} pts</span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-xs text-slate-500">Solde après</span>
                        <span class="text-sm font-bold text-emerald-700">{{ number_format($balance - $selectedReward->points_cost) }} pts</span>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mb-4">
                    @if ($selectedReward->reward_type === 'discount_code' || $selectedReward->reward_type === 'partner_voucher')
                        Vous recevrez un code par email immédiatement après confirmation.
                    @elseif ($selectedReward->reward_type === 'physical_item')
                        Veuillez vérifier votre adresse de livraison dans votre profil.
                    @elseif ($selectedReward->reward_type === 'service_credit')
                        Le crédit sera appliqué à votre prochaine prestation.
                    @endif
                </p>
                <div class="flex justify-end gap-2">
                    <button wire:click="closeReward" class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-700">Annuler</button>
                    <button wire:click="redeem({{ $selectedReward->id }})"
                            class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">
                        Confirmer l'échange
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
