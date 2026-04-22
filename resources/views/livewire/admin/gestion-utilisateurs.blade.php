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
                        <button wire:click="toggleActivation({{ $u->id }})"
                                class="text-xs bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">
                            {{ $u->active ? __('ui.admin_users.deactivate') : __('ui.admin_users.activate') }}
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-3">
        {{ $users->links() }}
    </div>
</div>
