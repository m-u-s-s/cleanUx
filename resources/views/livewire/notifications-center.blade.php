<div class="space-y-6" data-phase2t-root="true">
    @includeIf('livewire.shared.communication.layout-stack')

    <div class="max-w-6xl mx-auto space-y-6 px-4 py-8">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="ui-page-eyebrow !mt-0">Notifications</p>
                <h1 class="ui-page-title">{{ __('ui.notifications.center_title') }}</h1>
                <p class="ui-page-subtitle">{{ __('ui.notifications.center_subtitle') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 border border-brand-200 px-3 py-1 text-xs font-semibold text-brand-700">
                    <x-ui.icon name="bell" class="w-3.5 h-3.5" />
                    {{ __('ui.notifications.unread_count', ['count' => $unreadCount]) }}
                </span>
                @if($unreadCount > 0)
                    <button wire:click="markAllAsRead" class="brio-btn-primary inline-flex items-center gap-2 !py-2 !text-xs">
                        <x-ui.icon name="check" class="w-3.5 h-3.5" />
                        <span>{{ __('ui.notifications.mark_all_read') }}</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Filtres --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-soft-sm">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="md:col-span-2 relative">
                    <x-ui.icon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                    <input wire:model.live.debounce.300ms="search" type="text"
                           placeholder="{{ __('ui.notifications.search_placeholder') }}"
                           class="ui-input !pl-9" />
                </div>

                <select wire:model.live="filter" class="ui-input">
                    <option value="all">{{ __('ui.notifications.all') }}</option>
                    <option value="unread">{{ __('ui.notifications.unread') }}</option>
                    <option value="read">{{ __('ui.notifications.read') }}</option>
                </select>
            </div>
        </div>

        {{-- Liste --}}
        <div class="space-y-2.5">
            @forelse($notifications as $notification)
                @php
                    $data = $notification->data ?? [];
                    $message = $data['message'] ?? __('ui.notifications.item_fallback');
                    $context = $data['type'] ?? class_basename($notification->type ?? __('ui.notifications.item_fallback'));
                    $isUnread = is_null($notification->read_at);
                @endphp

                <div class="rounded-xl border {{ $isUnread ? 'border-brand-200 bg-brand-50/40' : 'border-slate-200/70 bg-white' }} p-4 shadow-soft-sm transition hover:shadow-soft">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div class="space-y-1.5 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                                    {{ $context }}
                                </span>
                                @if($isUnread)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                                        <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></span>
                                        {{ __('ui.notifications.new') }}
                                    </span>
                                @endif
                            </div>

                            <p class="text-sm font-medium text-slate-900">{{ $message }}</p>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                @if(!empty($data['rdv_id']))
                                    <span class="inline-flex items-center gap-1">
                                        <x-ui.icon name="briefcase" class="w-3 h-3" />
                                        #{{ $data['rdv_id'] }}
                                    </span>
                                @endif

                                <span class="inline-flex items-center gap-1">
                                    <x-ui.icon name="clock" class="w-3 h-3" />
                                    {{ $notification->created_at?->diffForHumans() }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-1.5 shrink-0">
                            @if($isUnread)
                                <button wire:click="markAsRead('{{ $notification->id }}')"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 transition">
                                    <x-ui.icon name="check" class="w-3.5 h-3.5" />
                                    <span class="hidden sm:inline">{{ __('ui.notifications.mark_read') }}</span>
                                </button>
                            @else
                                <button wire:click="markAsUnread('{{ $notification->id }}')"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 border border-amber-200 px-2.5 py-1.5 text-xs font-semibold text-amber-700 transition">
                                    <x-ui.icon name="bell" class="w-3.5 h-3.5" />
                                    <span class="hidden sm:inline">{{ __('ui.notifications.mark_unread') }}</span>
                                </button>
                            @endif

                            <button wire:click="deleteNotification('{{ $notification->id }}')"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition">
                                <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                                <span class="hidden sm:inline">{{ __('ui.notifications.delete') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-12 text-center">
                    <div class="grid h-12 w-12 mx-auto place-items-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                        <x-ui.icon name="bell" class="w-6 h-6" />
                    </div>
                    <p class="text-slate-700 font-semibold">{{ __('ui.notifications.none_filtered') }}</p>
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div>
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
