<div class="space-y-6" data-phase2t-root="true">
    @includeIf('livewire.shared.communication.layout-stack')

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ __('ui.notifications.center_title') }}</h1>
            <p class="text-sm text-slate-500">{{ __('ui.notifications.center_subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                {{ __('ui.notifications.unread_count', ['count' => $unreadCount]) }}
            </span>
            @if($unreadCount > 0)
                <button wire:click="markAllAsRead" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ __('ui.notifications.mark_all_read') }}
                </button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('ui.notifications.search_placeholder') }}"
               class="rounded-xl border-slate-300 shadow-sm md:col-span-2" />

        <select wire:model.live="filter" class="rounded-xl border-slate-300 shadow-sm">
            <option value="all">{{ __('ui.notifications.all') }}</option>
            <option value="unread">{{ __('ui.notifications.unread') }}</option>
            <option value="read">{{ __('ui.notifications.read') }}</option>
        </select>
    </div>

    <div class="space-y-3">
        @forelse($notifications as $notification)
            @php
                $data = $notification->data ?? [];
                $message = $data['message'] ?? __('ui.notifications.item_fallback');
                $context = $data['type'] ?? class_basename($notification->type ?? __('ui.notifications.item_fallback'));
                $isUnread = is_null($notification->read_at);
            @endphp

            <div class="rounded-2xl border p-4 shadow-sm {{ $isUnread ? 'border-blue-200 bg-blue-50' : 'border-slate-200 bg-white' }}">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                {{ $context }}
                            </span>
                            @if($isUnread)
                                <span class="inline-flex items-center rounded-full bg-blue-600 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-white">
                                    {{ __('ui.notifications.new') }}
                                </span>
                            @endif
                        </div>

                        <p class="text-sm font-medium text-slate-900">{{ $message }}</p>

                        @if(!empty($data['rdv_id']))
                            <p class="text-xs text-slate-500">{{ __('ui.notifications.booking_prefix') }} #{{ $data['rdv_id'] }}</p>
                        @endif

                        <p class="text-xs text-slate-400">{{ $notification->created_at?->diffForHumans() }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if($isUnread)
                            <button wire:click="markAsRead('{{ $notification->id }}')" class="rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700">
                                {{ __('ui.notifications.mark_read') }}
                            </button>
                        @else
                            <button wire:click="markAsUnread('{{ $notification->id }}')" class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-600">
                                {{ __('ui.notifications.mark_unread') }}
                            </button>
                        @endif

                        <button wire:click="deleteNotification('{{ $notification->id }}')" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">
                            {{ __('ui.notifications.delete') }}
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm italic text-slate-500">
                {{ __('ui.notifications.none_filtered') }}
            </div>
        @endforelse
    </div>

    <div>
        {{ $notifications->links() }}
    </div>
</div>

</div>