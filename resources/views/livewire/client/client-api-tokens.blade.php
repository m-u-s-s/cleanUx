<div class="py-6">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">API Tokens</p>
            <h1 class="text-2xl font-black text-slate-900">Mes tokens d'API</h1>
            <p class="text-sm text-slate-500">Gérez les credentials pour intégrer CleanUx à vos systèmes (Webhooks, lecture bookings, etc.).</p>
        </div>

        @if($justCreatedToken)
            <div class="rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <p class="text-sm font-bold text-emerald-800 uppercase">⚠️ Copiez ce token MAINTENANT</p>
                        <p class="text-sm text-emerald-900 mt-1">Il ne sera plus jamais affiché. Stockez-le dans un secret manager sécurisé.</p>
                        <pre class="mt-3 rounded-lg bg-slate-900 text-emerald-300 p-3 text-xs font-mono overflow-x-auto select-all">{{ $justCreatedToken }}</pre>
                        @if($justCreatedMeta && isset($justCreatedMeta['expires_at']))
                            <p class="text-xs text-emerald-700 mt-2">Expire le {{ \Illuminate\Support\Carbon::parse($justCreatedMeta['expires_at'])->format('d/m/Y') }}</p>
                        @endif
                    </div>
                    <button wire:click="dismissNewToken" class="text-emerald-600 hover:text-emerald-900 text-sm font-bold">✕</button>
                </div>
            </div>
        @endif

        @if($justRotatedToken)
            <div class="rounded-2xl border-2 border-blue-300 bg-blue-50 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <p class="text-sm font-bold text-blue-800 uppercase">🔄 Nouveau token après rotation</p>
                        <p class="text-sm text-blue-900 mt-1">L'ancien token reste valide pendant la grace period. Mettez à jour votre intégration avant expiration.</p>
                        <pre class="mt-3 rounded-lg bg-slate-900 text-blue-300 p-3 text-xs font-mono overflow-x-auto select-all">{{ $justRotatedToken }}</pre>
                        @if($justRotatedMeta && isset($justRotatedMeta['grace_until']))
                            <p class="text-xs text-blue-700 mt-2">Ancien token valide jusqu'au {{ \Illuminate\Support\Carbon::parse($justRotatedMeta['grace_until'])->format('d/m/Y H:i') }}</p>
                        @endif
                    </div>
                    <button wire:click="dismissRotatedToken" class="text-blue-600 hover:text-blue-900 text-sm font-bold">✕</button>
                </div>
            </div>
        @endif

        {{-- Création --}}
        <div class="rounded-2xl border bg-white shadow-sm p-5">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Créer un nouveau token</h2>
            <form wire:submit.prevent="createToken" class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Nom *</label>
                        <input type="text" wire:model="newName" placeholder="ex: Acme prod" maxlength="191"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                        @error('newName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Description</label>
                        <input type="text" wire:model="newDescription" placeholder="ex: Intégration ERP"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold uppercase text-slate-500 mb-2 block">Scopes (permissions) *</label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        @foreach($this->availableScopes as $scope)
                            <label class="flex items-start gap-2 rounded-lg border bg-white p-2 hover:bg-slate-50">
                                <input type="checkbox" wire:model="newScopes" value="{{ $scope->code }}"
                                       class="mt-0.5 rounded border-gray-300" />
                                <div>
                                    <span class="block text-xs font-mono font-bold">{{ $scope->code }}</span>
                                    <span class="block text-xs text-slate-500">{{ $scope->description }}</span>
                                    @if($scope->is_dangerous)
                                        <span class="text-xs text-red-600 font-bold">⚠️ Dangereux</span>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('newScopes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Expiration (jours)</label>
                        <input type="number" wire:model="newExpiryDays" min="1" max="3650"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Rate limit (req/min)</label>
                        <input type="number" wire:model="newRateLimit" min="1" max="10000" placeholder="défaut (120)"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    </div>
                </div>

                <button type="submit" class="rounded-xl bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700">
                    Créer le token
                </button>
            </form>
        </div>

        {{-- Liste --}}
        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Nom</th>
                        <th class="px-4 py-2 text-left">Scopes</th>
                        <th class="px-4 py-2 text-left">Rate</th>
                        <th class="px-4 py-2 text-left">Usage</th>
                        <th class="px-4 py-2 text-left">Expire</th>
                        <th class="px-4 py-2 text-left">Statut</th>
                        <th class="px-4 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($this->myTokens as $t)
                        <tr>
                            <td class="px-4 py-2 text-xs">{{ $t->display_name ?: $t->name }}</td>
                            <td class="px-4 py-2 text-xs">
                                <div class="flex flex-wrap gap-1">
                                    @foreach((array) ($t->abilities ?? []) as $scope)
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px]">{{ $scope }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-2 text-xs">{{ $t->effectiveRateLimit() }}/min</td>
                            <td class="px-4 py-2 text-xs">{{ number_format($t->usage_count) }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ optional($t->expires_at)->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs">
                                @if($t->isSuspended())
                                    <span class="text-amber-600">⊘ suspendu</span>
                                @elseif($t->isExpired())
                                    <span class="text-red-600">✗ expiré</span>
                                @elseif($t->isRotatedExpired())
                                    <span class="text-slate-500">↻ rotated</span>
                                @else
                                    <span class="text-emerald-600">✓ actif</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right text-xs space-x-2">
                                <button wire:click="rotate({{ $t->id }})"
                                        class="text-indigo-600 hover:underline"
                                        onclick="return confirm('Rotation : nouveau token + ancien valide pendant la grace period 24h.')">Rotate</button>
                                <button wire:click="revoke({{ $t->id }})"
                                        class="text-red-600 hover:underline"
                                        onclick="return confirm('Révoquer définitivement ce token ?')">Révoquer</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun token. Créez votre premier ci-dessus.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>
