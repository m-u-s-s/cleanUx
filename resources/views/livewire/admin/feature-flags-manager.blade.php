<div class="p-6 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Feature Flags</h1>
            <p class="mt-1 text-sm text-gray-500">
                Toggle runtime flags. DB overrides take precedence over <code>config/features.php</code>.
            </p>
        </div>
        <div class="w-64">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search flags..."
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
            >
        </div>
    </div>

    {{-- Flash --}}
    @if (session()->has('success'))
        <div class="rounded-md bg-green-50 border border-green-200 p-4 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Edit modal --}}
    @if ($editingKey)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl space-y-4">
                <h2 class="text-lg font-semibold text-gray-900">Edit flag: <code class="text-indigo-600">{{ $editingKey }}</code></h2>

                <div class="flex items-center gap-3">
                    <span id="drapeau-actif" class="text-sm font-medium text-gray-700">Enabled</span>
                    <button
                        wire:click="$toggle('editingValue')"
                                    aria-labelledby="drapeau-actif"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none {{ $editingValue ? 'bg-indigo-600' : 'bg-gray-300' }}"
                        role="switch"
                        aria-checked="{{ $editingValue ? 'true' : 'false' }}"
                    >
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 {{ $editingValue ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                    <span class="text-sm {{ $editingValue ? 'text-green-600 font-medium' : 'text-gray-400' }}">
                        {{ $editingValue ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="editReason">Reason (optional)</label>
                    <input id="editReason"
                        type="text"
                        wire:model="editReason"
                        placeholder="Why are you changing this flag?"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>

                <div class="flex gap-3 justify-end">
                    <button wire:click="$set('editingKey', null)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button wire:click="saveFlag" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Save
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Flags table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="brio-table-cadre"><table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Flag key</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Config</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Active state</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Override</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Last change</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($flags as $flag)
                    <tr class="hover:bg-gray-50 transition-colors">
                        {{-- Key --}}
                        <td class="px-4 py-3">
                            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-mono text-gray-800">{{ $flag['key'] }}</code>
                        </td>

                        {{-- Config value --}}
                        <td class="px-4 py-3 text-gray-500 text-xs font-mono">
                            @if(is_bool($flag['config_value']))
                                {{ $flag['config_value'] ? 'true' : 'false' }}
                            @else
                                <span title="{{ json_encode($flag['config_value']) }}">array</span>
                            @endif
                        </td>

                        {{-- Resolved state + quick toggle --}}
                        <td class="px-4 py-3">
                            <button
                                wire:click="toggleFlag('{{ $flag['key'] }}')"
                                wire:loading.attr="disabled"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none {{ $flag['resolved_enabled'] ? 'bg-green-500' : 'bg-gray-300' }}"
                                role="switch"
                                aria-checked="{{ $flag['resolved_enabled'] ? 'true' : 'false' }}"
                                title="Click to toggle"
                            >
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 {{ $flag['resolved_enabled'] ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </td>

                        {{-- Override badge --}}
                        <td class="px-4 py-3">
                            @if ($flag['has_override'])
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                    DB override
                                </span>
                                @if ($flag['override_reason'])
                                    <p class="mt-0.5 text-xs text-gray-400 truncate max-w-[160px]" title="{{ $flag['override_reason'] }}">
                                        {{ $flag['override_reason'] }}
                                    </p>
                                @endif
                            @else
                                <span class="text-xs text-gray-400">config file</span>
                            @endif
                        </td>

                        {{-- Last change --}}
                        <td class="px-4 py-3 text-xs text-gray-500">
                            @if ($flag['updated_at'])
                                <div>{{ \Carbon\Carbon::parse($flag['updated_at'])->diffForHumans() }}</div>
                                @if ($flag['updated_by'])
                                    <div class="text-gray-400">by {{ $flag['updated_by'] }}</div>
                                @endif
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3 text-right space-x-2">
                            <button
                                wire:click="editFlag('{{ $flag['key'] }}')"
                                class="rounded px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50 border border-indigo-200"
                            >
                                Edit
                            </button>
                            @if ($flag['has_override'])
                                <button
                                    wire:click="removeOverride('{{ $flag['key'] }}')"
                                    wire:confirm="Remove DB override? The config file value will apply again."
                                    class="rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 border border-red-200"
                                >
                                    Reset
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                            No feature flags found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table></div>
    </div>

    <p class="text-xs text-gray-400">
        Flags are defined in <code>config/features.php</code>. DB overrides persist across deploys.
        Use "Reset" to restore the config file value.
    </p>
</div>
