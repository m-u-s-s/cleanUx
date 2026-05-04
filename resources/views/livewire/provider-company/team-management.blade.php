<div class="min-h-screen bg-slate-900 text-slate-100 p-6">

    {{-- ── Header ── --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Gestion</p>
            <h1 class="text-2xl font-black text-white">👥 Équipe</h1>
        </div>
        <button wire:click="$set('showInvite', true)"
            class="flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            + Inviter un membre
        </button>
    </div>

    {{-- ── Filtres ── --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative">
            <input wire:model.live.debounce.300ms="searchQuery"
                type="text" placeholder="Rechercher…"
                class="w-48 rounded-xl border border-slate-700 bg-slate-800 pl-8 pr-3 py-2 text-sm text-white placeholder-slate-500 outline-none focus:border-blue-500">
            <span class="absolute left-2.5 top-2.5 text-slate-500 text-xs">🔍</span>
        </div>

        <select wire:model.live="filterRole"
            class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300 outline-none focus:border-blue-500">
            <option value="">Tous les rôles</option>
            @foreach ($availableRoles as $role)
                <option value="{{ $role->value }}">{{ $role->label() }}</option>
            @endforeach
        </select>

        <div class="flex rounded-xl border border-slate-700 overflow-hidden">
            @foreach (['active' => 'Actifs', 'suspended' => 'Suspendus', '' => 'Tous'] as $val => $label)
                <button wire:click="$set('filterStatus', '{{ $val }}')"
                    class="px-3 py-2 text-xs font-medium transition
                        {{ $filterStatus === $val
                            ? 'bg-blue-600 text-white'
                            : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <span class="ml-auto text-xs text-slate-500">{{ $members->count() }} membre(s)</span>
    </div>

    {{-- ── Tableau membres ── --}}
    <div class="rounded-2xl border border-slate-700 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-700 bg-slate-800/80">
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-400">Membre</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-400">Rôle</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-400">Statut</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-400">Rejoint le</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @forelse ($members as $member)
                    @php
                        $isSelf   = $member->user_id === Auth::id();
                        $isOwner  = $member->role->value === 'owner';
                        $statusDot = match ($member->status) {
                            'active'    => 'bg-emerald-400',
                            'invited'   => 'bg-yellow-400',
                            'suspended' => 'bg-red-500',
                            default     => 'bg-slate-500',
                        };
                        $statusLabel = match ($member->status) {
                            'active'    => 'Actif',
                            'invited'   => 'Invité',
                            'suspended' => 'Suspendu',
                            'left'      => 'Parti',
                            default     => $member->status,
                        };
                    @endphp
                    <tr class="bg-slate-800/30 hover:bg-slate-800/60 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $member->user->profile_photo_url }}"
                                     alt="{{ $member->user->name }}"
                                     class="h-9 w-9 rounded-full object-cover border border-slate-600">
                                <div>
                                    <p class="text-sm font-semibold text-white flex items-center gap-1">
                                        {{ $member->user->name }}
                                        @if ($isSelf)
                                            <span class="text-[10px] text-slate-400">(vous)</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-400">{{ $member->user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if (! $isSelf && ! $isOwner)
                                <select wire:change="changeRole({{ $member->id }}, $event.target.value)"
                                    class="rounded-lg border border-slate-600 bg-slate-700 px-2 py-1 text-xs text-slate-200 outline-none focus:border-blue-500">
                                    @foreach ($availableRoles as $role)
                                        <option value="{{ $role->value }}"
                                            {{ $member->role->value === $role->value ? 'selected' : '' }}>
                                            {{ $role->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <span class="rounded-full bg-slate-700 px-2 py-1 text-xs font-semibold text-slate-300">
                                    {{ $member->roleLabel() }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="flex items-center gap-1.5 text-xs text-slate-300">
                                <span class="h-2 w-2 rounded-full {{ $statusDot }}"></span>
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-400">
                            {{ $member->joined_at?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button wire:click="openPermissions({{ $member->id }})"
                                    title="Permissions"
                                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-700 hover:text-blue-400">
                                    🔑
                                </button>
                                @if (! $isSelf && ! $isOwner)
                                    @if ($member->status === 'active')
                                        <button wire:click="suspend({{ $member->id }})"
                                            wire:confirm="Suspendre {{ $member->user->name }} ?"
                                            title="Suspendre"
                                            class="rounded-lg p-1.5 text-slate-400 hover:bg-amber-900/40 hover:text-amber-400">
                                            ⏸️
                                        </button>
                                    @else
                                        <button wire:click="reactivate({{ $member->id }})"
                                            title="Réactiver"
                                            class="rounded-lg p-1.5 text-slate-400 hover:bg-green-900/40 hover:text-green-400">
                                            ▶️
                                        </button>
                                    @endif
                                    <button wire:click="remove({{ $member->id }})"
                                        wire:confirm="Retirer {{ $member->user->name }} de l'organisation ?"
                                        title="Retirer"
                                        class="rounded-lg p-1.5 text-slate-400 hover:bg-red-900/40 hover:text-red-400">
                                        ✕
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-500">
                            <p class="text-2xl mb-2">👥</p>
                            <p class="text-sm">Aucun membre trouvé</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Modal Invitation ── --}}
@if ($showInvite)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-md rounded-2xl bg-slate-800 border border-slate-700 shadow-2xl">
            <div class="border-b border-slate-700 px-6 py-4 flex items-center justify-between">
                <h3 class="text-base font-black text-white">Inviter un membre</h3>
                <button wire:click="$set('showInvite', false)" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Email *</label>
                    <input wire:model="inviteEmail" type="email" placeholder="jean@example.com"
                        class="w-full rounded-xl border border-slate-600 bg-slate-700 px-3 py-2 text-sm text-white outline-none focus:border-blue-500">
                    @error('inviteEmail') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-400 mb-2">Rôle *</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($availableRoles as $role)
                            @if ($role->value !== 'owner')
                                <label class="cursor-pointer rounded-xl border px-3 py-2 text-sm transition
                                    {{ $inviteRole === $role->value
                                        ? 'border-blue-500 bg-blue-900/30 text-blue-300'
                                        : 'border-slate-600 bg-slate-700/50 text-slate-300 hover:border-slate-500' }}">
                                    <input type="radio" wire:model="inviteRole" value="{{ $role->value }}" class="sr-only">
                                    {{ $role->label() }}
                                </label>
                            @endif
                        @endforeach
                    </div>
                    @error('inviteRole') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Note (optionnel)</label>
                    <textarea wire:model="inviteNote" rows="2" placeholder="Message d'accueil…"
                        class="w-full resize-none rounded-xl border border-slate-600 bg-slate-700 px-3 py-2 text-sm text-white outline-none focus:border-blue-500"></textarea>
                </div>
            </div>
            <div class="flex gap-3 border-t border-slate-700 p-4">
                <button wire:click="$set('showInvite', false)"
                    class="flex-1 rounded-xl border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700">
                    Annuler
                </button>
                <button wire:click="invite"
                    class="flex-1 rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">
                    Inviter
                </button>
            </div>
        </div>
    </div>
@endif

{{-- ── Modal Permissions ── --}}
@if ($showPermissions && $editingMember)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm p-4">
        <div class="mx-auto my-8 w-full max-w-lg rounded-2xl bg-slate-800 border border-slate-700 shadow-2xl">
            <div class="border-b border-slate-700 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="{{ $editingMember->user->profile_photo_url }}"
                         class="h-8 w-8 rounded-full object-cover">
                    <div>
                        <p class="text-sm font-bold text-white">{{ $editingMember->user->name }}</p>
                        <p class="text-xs text-slate-400">{{ $editingMember->roleLabel() }}</p>
                    </div>
                </div>
                <button wire:click="$set('showPermissions', false)" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <div class="p-6">
                <p class="mb-4 text-xs text-slate-400">
                    Les permissions activées ici <strong class="text-slate-200">s'ajoutent ou remplacent</strong> celles du rôle.
                </p>
                @php
                    $memberPerms = $editingMember->allPermissions();
                    $grouped = collect($allPermissions)->groupBy(fn ($p) => explode('.', $p)[0]);
                @endphp
                <div class="space-y-4 max-h-80 overflow-y-auto pr-1">
                    @foreach ($grouped as $domain => $perms)
                        <div>
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">{{ strtoupper($domain) }}</p>
                            <div class="space-y-1">
                                @foreach ($perms as $perm)
                                    @php
                                        $currentVal  = $memberPerms[$perm] ?? false;
                                        $customPerms = $editingMember->permissions ?? [];
                                        $isCustomized = array_key_exists($perm, $customPerms);
                                    @endphp
                                    <div class="flex items-center justify-between rounded-lg px-3 py-2
                                        {{ $isCustomized ? 'bg-blue-900/20 border border-blue-500/20' : 'bg-slate-700/40' }}">
                                        <span class="text-xs text-slate-300">
                                            {{ str($perm)->after('.')->replace('_', ' ')->title() }}
                                            @if ($isCustomized)
                                                <span class="ml-1 text-[9px] text-blue-400">personnalisé</span>
                                            @endif
                                        </span>
                                        <button
                                            wire:click="togglePermission('{{ $perm }}', {{ $currentVal ? 'false' : 'true' }})"
                                            class="relative h-5 w-9 rounded-full transition-colors
                                                {{ $currentVal ? 'bg-blue-600' : 'bg-slate-600' }}"
                                        >
                                            <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform
                                                {{ $currentVal ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="border-t border-slate-700 p-4">
                <button wire:click="$set('showPermissions', false)"
                    class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">
                    Fermer
                </button>
            </div>
        </div>
    </div>
@endif
