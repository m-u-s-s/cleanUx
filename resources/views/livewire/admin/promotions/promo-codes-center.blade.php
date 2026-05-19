<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Promotions</p>
                <h1 class="text-2xl font-black text-slate-900">Codes promo</h1>
                <p class="text-sm text-slate-500">Créez, suivez et désactivez les codes promo de la plateforme.</p>
            </div>

            <a href="{{ route('admin.dashboard') }}"
               class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                ← Dashboard
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Formulaire création/édition --}}
            <div class="lg:col-span-1 rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">
                        {{ $editingId ? 'Modifier le code' : 'Nouveau code promo' }}
                    </h2>
                    @if($editingId)
                        <button wire:click="resetForm" class="text-xs text-slate-500 hover:underline">Annuler</button>
                    @endif
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Code</label>
                    <div class="flex gap-2 mt-1">
                        <input type="text" wire:model="code"
                               class="flex-1 rounded-xl border-gray-300 text-sm uppercase"
                               placeholder="SUMMER25" />
                        <button type="button" wire:click="generateRandomCode"
                                class="rounded-xl bg-slate-100 px-3 text-xs font-semibold hover:bg-slate-200">
                            Aléatoire
                        </button>
                    </div>
                    @error('code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Nom interne (optionnel)</label>
                    <input type="text" wire:model="name" class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Type</label>
                        <select wire:model.live="discount_type" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                            <option value="percent">Pourcentage (%)</option>
                            <option value="fixed_amount">Montant fixe</option>
                            <option value="free_first_booking">1ère réservation gratuite</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Valeur</label>
                        <input type="number" step="0.01" wire:model="discount_value"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                        @error('discount_value') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Plafond remise</label>
                        <input type="number" step="0.01" wire:model="max_discount_amount"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" placeholder="—" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Min. réservation</label>
                        <input type="number" step="0.01" wire:model="min_booking_amount"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" placeholder="—" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Max usages total</label>
                        <input type="number" wire:model="max_total_uses"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" placeholder="∞" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Max par utilisateur</label>
                        <input type="number" wire:model="max_uses_per_user"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Valide du</label>
                        <input type="datetime-local" wire:model="valid_from"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Valide jusqu'au</label>
                        <input type="datetime-local" wire:model="valid_until"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Audience</label>
                    <select wire:model="audience_scope" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                        <option value="all">Tous</option>
                        <option value="new_customers">Nouveaux clients</option>
                        <option value="returning_customers">Clients existants</option>
                        <option value="b2b">B2B uniquement</option>
                        <option value="specific_users">Utilisateurs spécifiques</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Campagne</label>
                    <select wire:model="promo_campaign_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                        <option value="">— Aucune —</option>
                        @foreach($campaigns as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="first_booking_only" class="rounded" />
                        Réservé à la 1ère réservation
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="stackable_with_credits" class="rounded" />
                        Cumulable avec les crédits clients
                    </label>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Statut</label>
                    <select wire:model="status" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                        <option value="draft">Brouillon</option>
                        <option value="active">Actif</option>
                        <option value="paused">En pause</option>
                        <option value="expired">Expiré</option>
                        <option value="archived">Archivé</option>
                    </select>
                </div>

                <button wire:click="save"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    {{ $editingId ? 'Enregistrer' : 'Créer le code' }}
                </button>
            </div>

            {{-- Liste --}}
            <div class="lg:col-span-2 rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <h2 class="text-lg font-bold text-slate-900">Codes existants</h2>

                    <div class="flex gap-2">
                        <input type="text" wire:model.live.debounce.300ms="search"
                               placeholder="Rechercher…"
                               class="rounded-xl border-gray-300 text-sm" />
                        <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                            <option value="">Tous statuts</option>
                            <option value="active">Actifs</option>
                            <option value="paused">En pause</option>
                            <option value="draft">Brouillon</option>
                            <option value="expired">Expirés</option>
                            <option value="archived">Archivés</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Code</th>
                                <th class="px-3 py-2 text-left">Remise</th>
                                <th class="px-3 py-2 text-left">Usages</th>
                                <th class="px-3 py-2 text-left">Validité</th>
                                <th class="px-3 py-2 text-left">Campagne</th>
                                <th class="px-3 py-2 text-left">Statut</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($promos as $p)
                                <tr>
                                    <td class="px-3 py-2 font-mono font-bold">{{ $p->code }}</td>
                                    <td class="px-3 py-2">
                                        @if($p->discount_type === 'percent')
                                            -{{ rtrim(rtrim(number_format((float)$p->discount_value, 2, ',', ' '), '0'), ',') }} %
                                        @elseif($p->discount_type === 'fixed_amount')
                                            -{{ number_format((float)$p->discount_value, 2, ',', ' ') }} €
                                        @else
                                            Gratuit (1ère)
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ $p->total_uses }}{{ $p->max_total_uses ? ' / '.$p->max_total_uses : '' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-500">
                                        @if($p->valid_until)
                                            jusqu'au {{ $p->valid_until->format('d/m/Y') }}
                                        @else
                                            illimité
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">{{ $p->campaign?->name ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                            'bg-emerald-100 text-emerald-800' => $p->status === 'active',
                                            'bg-amber-100 text-amber-800' => $p->status === 'paused',
                                            'bg-slate-100 text-slate-700' => in_array($p->status, ['draft','expired','archived']),
                                        ])>{{ $p->status }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right space-x-1">
                                        <button wire:click="edit({{ $p->id }})"
                                                class="text-xs font-semibold text-indigo-600 hover:underline">Modifier</button>
                                        @if($p->status !== 'active')
                                            <button wire:click="activate({{ $p->id }})"
                                                    class="text-xs font-semibold text-emerald-600 hover:underline">Activer</button>
                                        @endif
                                        @if($p->status !== 'archived')
                                            <button wire:click="archive({{ $p->id }})"
                                                    class="text-xs font-semibold text-red-600 hover:underline">Archiver</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-3 py-6 text-center text-slate-400">Aucun code promo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>{{ $promos->links() }}</div>
            </div>
        </div>
    </div>
</div>
