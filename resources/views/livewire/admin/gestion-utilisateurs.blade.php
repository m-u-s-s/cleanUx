<div class="bg-white p-4 rounded shadow space-y-4">

    <h3 class="text-xl font-semibold text-blue-800">👥 {{ __('ui.admin_users.title') }}</h3>

    <div class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="text-sm text-gray-600">{{ __('ui.admin_users.role') }}</label>
            <select wire:model="roleFilter" class="border rounded px-2 py-1 text-sm">
                <option value="">{{ __('ui.admin_users.all') }}</option>
                <option value="client">{{ __('ui.admin_users.client') }}</option>
                <option value="employe">{{ __('ui.admin_users.employee') }}</option>
                <option value="entreprise">{{ __('ui.admin_users.company') }}</option>
            </select>
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __('ui.admin_users.search') }}</label>
            <input type="text" wire:model.debounce.300ms="search"
                   placeholder="{{ __('ui.admin_users.search_placeholder') }}"
                   class="border rounded px-2 py-1 text-sm" />
        </div>
    </div>

    <table class="w-full text-sm table-auto border mt-3">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-2 py-1 text-left">{{ __('ui.admin_users.name') }}</th>
                <th class="px-2 py-1">{{ __('ui.admin_users.email') }}</th>
                <th class="px-2 py-1">{{ __('ui.admin_users.role') }}</th>
                <th class="px-2 py-1">{{ __('ui.admin_users.active') }}</th>
                <th class="px-2 py-1">{{ __('ui.admin_users.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $u)
                <tr class="border-t">
                    <td class="px-2 py-1">{{ $u->name }}</td>
                    <td class="px-2 py-1">{{ $u->email }}</td>
                    <td class="px-2 py-1">
                        <select wire:change="updateRole({{ $u->id }}, $event.target.value)"
                                class="border px-1 text-sm">
                            <option value="client" @selected($u->role === 'client')>{{ __('ui.admin_users.client') }}</option>
                            <option value="employe" @selected($u->role === 'employe')>{{ __('ui.admin_users.employee') }}</option>
                            <option value="entreprise" @selected($u->role === 'entreprise')>{{ __('ui.admin_users.company') }}</option>
                        </select>
                    </td>
                    <td class="px-2 py-1">
                        @if($u->active)
                            <span class="text-green-600 font-semibold">{{ __('ui.admin_users.yes') }}</span>
                        @else
                            <span class="text-red-600 font-semibold">{{ __('ui.admin_users.no') }}</span>
                        @endif
                    </td>
                    <td class="px-2 py-1">
                        <div class="flex flex-wrap gap-1">
                            <button wire:click="toggleActivation({{ $u->id }})"
                                    class="text-xs bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">
                                {{ $u->active ? __('ui.admin_users.deactivate') : __('ui.admin_users.activate') }}
                            </button>

                            @if(in_array($u->role, [\App\Models\User::ROLE_EMPLOYE, \App\Models\User::ROLE_PROVIDER], true))
                                <button wire:click="openEmployeeTrades({{ $u->id }})"
                                        class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded hover:bg-blue-200">
                                    🧰 {{ $u->trades_count ?? $u->trades->count() }} métier(s)
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-3">
        {{ $users->links() }}
    </div>

    {{-- ─────────────────────────────────────────────── --}}
    {{-- Modal : assignation des métiers à un employé    --}}
    {{-- ─────────────────────────────────────────────── --}}
    @if($editingTradesUserId)
        @php($editingUser = $users->firstWhere('id', $editingTradesUserId))
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" wire:click.self="cancelEmployeeTrades">
            <div class="relative w-full max-w-2xl overflow-hidden rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">🧰 Métiers de l'utilisateur</h2>
                        @if($editingUser)
                            <p class="text-xs text-gray-500">{{ $editingUser->name }} · {{ $editingUser->email }}</p>
                        @endif
                    </div>
                    <button wire:click="cancelEmployeeTrades" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <div class="space-y-3 px-6 py-5 max-h-[60vh] overflow-y-auto">
                    @forelse($allAvailableTrades as $trade)
                        @php($entry = $employeeTradesSelection[$trade->id] ?? ['selected' => false, 'proficiency' => '', 'notes' => ''])
                        <div class="rounded-md border p-3 {{ ($entry['selected'] ?? false) ? 'border-blue-300 bg-blue-50' : 'border-gray-200' }}">
                            <div class="flex items-start justify-between gap-3">
                                <label class="inline-flex items-start gap-2 flex-1 cursor-pointer">
                                    <input type="checkbox"
                                        @checked($entry['selected'] ?? false)
                                        wire:click="toggleEmployeeTrade({{ $trade->id }})"
                                        class="mt-1 rounded text-blue-600">
                                    <span>
                                        <span class="font-semibold text-gray-900">{{ $trade->name }}</span>
                                        <span class="text-xs text-gray-500">· {{ $trade->slug }}</span>
                                    </span>
                                </label>

                                @if($entry['selected'] ?? false)
                                    <button type="button"
                                        wire:click="setEmployeeTradePrimary({{ $trade->id }})"
                                        class="text-xs px-2 py-1 rounded transition
                                            {{ $employeeTradesPrimary === $trade->id
                                                ? 'bg-amber-200 text-amber-900 font-semibold'
                                                : 'bg-gray-100 text-gray-600 hover:bg-amber-100' }}">
                                        {{ $employeeTradesPrimary === $trade->id ? '★ Principal' : 'Définir principal' }}
                                    </button>
                                @endif
                            </div>

                            @if($entry['selected'] ?? false)
                                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs text-gray-600">Niveau</label>
                                        <select wire:model="employeeTradesSelection.{{ $trade->id }}.proficiency"
                                                class="block w-full rounded border-gray-300 text-sm">
                                            <option value="">— Non précisé —</option>
                                            <option value="basic">Débutant</option>
                                            <option value="standard">Confirmé</option>
                                            <option value="expert">Expert</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600">Notes internes</label>
                                        <input type="text"
                                            wire:model="employeeTradesSelection.{{ $trade->id }}.notes"
                                            placeholder="Ex: certification CACES R489"
                                            class="block w-full rounded border-gray-300 text-sm" />
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 italic">
                            Aucun métier actif disponible. Créez-en depuis Admin → Métiers.
                        </p>
                    @endforelse
                </div>

                <div class="flex justify-end gap-2 border-t bg-gray-50 px-6 py-3">
                    <button wire:click="cancelEmployeeTrades" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        Annuler
                    </button>
                    <button wire:click="saveEmployeeTrades" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
