<div x-data="{ open: false }" class="bg-white p-4 rounded shadow-md space-y-4">

    <div class="flex justify-between items-center">
        <h2 class="text-lg font-bold text-blue-900">📄 {{ __('ui.access_matrix.title') }}</h2>
        <button @click="open = !open"
                class="text-sm text-blue-600 hover:underline">
            <span x-show="!open">🔎 {{ __('ui.access_matrix.show') }}</span>
            <span x-show="open">🔽 {{ __('ui.access_matrix.hide') }}</span>
        </button>
    </div>

    <div x-show="open" x-transition>
        <table class="w-full text-sm table-auto border mt-3">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-2 py-2 text-left">{{ __('ui.access_matrix.feature') }}</th>
                    <th class="px-2 py-2 text-center">👑 {{ __('ui.access_matrix.admin') }}</th>
                    <th class="px-2 py-2 text-center">👨‍🔧 {{ __('ui.access_matrix.employee') }}</th>
                    <th class="px-2 py-2 text-center">👤 {{ __('ui.access_matrix.client') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach([
                    __('ui.access_matrix.dashboard') => [true, true, true],
                    __('ui.access_matrix.public_booking') => [false, false, true],
                    __('ui.access_matrix.see_bookings') => [false, true, true],
                    __('ui.access_matrix.validate_single') => [false, true, false],
                    __('ui.access_matrix.validate_bulk') => [false, true, false],
                    __('ui.access_matrix.leave_feedback') => [false, false, true],
                    __('ui.access_matrix.edit_feedback') => [false, false, true],
                    __('ui.access_matrix.see_feedback') => [true, true, true],
                    __('ui.access_matrix.global_notifications') => [true, true, true],
                    __('ui.access_matrix.export') => [true, false, false],
                    __('ui.access_matrix.import') => [true, false, false],
                    __('ui.access_matrix.stats') => [true, false, false],
                    __('ui.access_matrix.sessions') => [true, true, true],
                    __('ui.access_matrix.activity_logs') => [true, false, false],
                    __('ui.access_matrix.manage_users') => [true, false, false],
                    __('ui.access_matrix.edit_limits') => [true, true, false],
                ] as $label => [$admin, $employe, $client])
                    <tr>
                        <td class="px-2 py-2">{{ $label }}</td>
                        <td class="px-2 py-2 text-center">{!! $admin ? '✅' : '—' !!}</td>
                        <td class="px-2 py-2 text-center">{!! $employe ? '✅' : '—' !!}</td>
                        <td class="px-2 py-2 text-center">{!! $client ? '✅' : '—' !!}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
