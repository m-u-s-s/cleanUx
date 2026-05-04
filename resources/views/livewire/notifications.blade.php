<div class="space-y-6" data-phase2t-root="true">
    @includeIf('livewire.shared.communication.layout-stack')

<div class="bg-white rounded-xl shadow border p-4">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">{{ __('ui.notifications.title') }}</h3>
            <p class="text-sm text-gray-500">
                {{ __('ui.notifications.unread_count', ['count' => $unreadCount]) }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('notifications.index') }}" class="text-sm px-3 py-1 rounded border border-slate-300 text-slate-700 hover:bg-slate-50">
                {{ __('ui.notifications.view_all') }}
            </a>
            @if($unreadCount > 0)
                <button
                    wire:click="markAllAsRead"
                    class="text-sm px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">
                    {{ __('ui.notifications.mark_all_read') }}
                </button>
            @endif
        </div>
    </div>

    <div class="space-y-3">
        @forelse($notifications as $notification)
            @php
                $data = $notification->data ?? [];
                $message = $data['message'] ?? __('ui.notifications.item_fallback');
                $date = $notification->created_at?->diffForHumans();
                $isUnread = is_null($notification->read_at);
            @endphp

            <div class="border rounded-lg p-3 {{ $isUnread ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800">
                            {{ $message }}
                        </p>

                        @if(!empty($data['rdv_id']))
                            <p class="text-xs text-gray-500 mt-1">
                                {{ __('ui.notifications.booking_prefix') }} #{{ $data['rdv_id'] }}
                            </p>
                        @endif

                        <p class="text-xs text-gray-400 mt-2">
                            {{ $date }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($isUnread)
                            <button
                                wire:click="markAsRead('{{ $notification->id }}')"
                                class="text-xs px-2 py-1 rounded bg-green-600 text-white hover:bg-green-700">
                                {{ __('ui.notifications.mark_read') }}
                            </button>
                        @endif

                        <button
                            wire:click="deleteNotification('{{ $notification->id }}')"
                            class="text-xs px-2 py-1 rounded bg-red-600 text-white hover:bg-red-700">
                            {{ __('ui.notifications.delete') }}
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-sm text-gray-500">
                {{ __('ui.notifications.none') }}
            </div>
        @endforelse
    </div>
</div>

</div>