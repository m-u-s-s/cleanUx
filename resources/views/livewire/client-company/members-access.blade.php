<div class="min-h-screen bg-slate-50 p-6">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">👥 Membres de l'organisation</h1>
            <p class="text-sm text-slate-500">Gérez les accès et rôles de votre équipe</p>
        </div>
        <button wire:click="$set('showInvite', true)"
            class="flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">
            + Inviter un membre
        </button>
    </div>

    {{-- Membres --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-400">
                    <th class="px-5 py-3 text-left">Membre</th>
                    <th class="px-5 py-3 text-left hidden sm:table-cell">Rôle</th>
                    <th class="px-5 py-3 text-left hidden md:table-cell">Statut</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($members as $member)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <img src="{{ $member->user?->profile_photo_url }}"
                                     class="h-9 w-9 rounded-full object-cover border border-slate-200">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $member->user?->name }}</p>
                                    <p class="text-xs text-slate-400 truncate">{{ $member->user?->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 hidden sm:table-cell">
                            @if ($member->user_id !== Auth::id() && $member->role !== \App\Enums\OrganizationRole::OWNER)
                                <select wire:change="changeRole({{ $member->id }}, $event.target.value)"
                                    class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 outline-none focus:border-purple-500">
                                    @foreach ($availableRoles as $role)
                                        <option value="{{ $role->value }}"
                                            {{ $member->role->value === $role->value ? 'selected' : '' }}>
                                            {{ $role->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <span class="rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-700">
                                    {{ $member->role->label() }}
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 hidden md:table-cell">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold
                                {{ match($member->status) {
                                    'active'    => 'bg-green-100 text-green-700',
                                    'invited'   => 'bg-blue-100 text-blue-700',
                                    'suspended' => 'bg-red-100 text-red-700',
                                    default     => 'bg-slate-100 text-slate-500',
                                } }}">
                                {{ match($member->status) {
                                    'active' => 'Actif', 'invited' => 'Invité',
                                    'suspended' => 'Suspendu', default => $member->status,
                                } }}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-end gap-2">
                                @if ($member->user_id !== Auth::id() && $member->role !== \App\Enums\OrganizationRole::OWNER)
                                    <button wire:click="openPermissions({{ $member->id }})"
                                        class="rounded-lg px-2.5 py-1 text-xs text-slate-500 border border-slate-200 hover:bg-slate-50">
                                        🔐 Permissions
                                    </button>
                                    @if ($member->status === 'active')
                                        <button wire:click="suspend({{ $member->id }})" wire:confirm="Suspendre ce membre ?"
                                            class="rounded-lg px-2.5 py-1 text-xs text-amber-600 border border-amber-200 hover:bg-amber-50">
                                            Suspendre
                                        </button>
                                    @endif
                                    <button wire:click="remove({{ $member->id }})" wire:confirm="Retirer ce membre ?"
                                        class="rounded-lg px-2.5 py-1 text-xs text-red-500 border border-red-200 hover:bg-red-50">
                                        Retirer
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-16 text-center text-slate-400 text-sm">Aucun membre</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal invitation --}}
@if ($showInvite)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl p-6">
            <h3 class="mb-5 text-lg font-black text-slate-900">Inviter un membre</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Email *</label>
                    <input wire:model="inviteEmail" type="email" placeholder="jean@entreprise.com"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100">
                    @error('inviteEmail') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Rôle *</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($availableRoles as $role)
                            <label class="cursor-pointer rounded-xl border px-3 py-2 text-sm transition
                                {{ $inviteRole === $role->value
                                    ? 'border-purple-500 bg-purple-50 text-purple-700 font-semibold'
                                    : 'border-slate-200 hover:border-slate-300 text-slate-600' }}">
                                <input type="radio" wire:model="inviteRole" value="{{ $role->value }}" class="sr-only">
                                {{ $role->label() }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button wire:click="$set('showInvite', false)"
                    class="flex-1 rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    Annuler
                </button>
                <button wire:click="invite"
                    class="flex-1 rounded-xl bg-purple-600 px-4 py-2 text-sm font-bold text-white hover:bg-purple-700">
                    Inviter
                </button>
            </div>
        </div>
    </div>
@endif
