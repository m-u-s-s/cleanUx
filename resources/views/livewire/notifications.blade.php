<div class="space-y-6" data-phase2t-root="true">
    @includeIf('livewire.shared.communication.layout-stack')

    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-soft p-5">
        <div class="flex items-start justify-between mb-4 gap-3">
            <div class="min-w-0">
                <h3 class="inline-flex items-center gap-2 text-base font-bold text-slate-900">
                    <x-ui.icon name="bell" class="w-4 h-4 text-brand-600" />
                    {{ __('ui.notifications.title') }}
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ __('ui.notifications.unread_count', ['count' => $unreadCount]) }}
                </p>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
                <a href="{{ route('notifications.index') }}"
                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                    <span class="hidden sm:inline">{{ __('ui.notifications.view_all') }}</span>
                    <x-ui.icon name="arrow-right" class="w-3 h-3" />
                </a>
                @if($unreadCount > 0)
                    <button wire:click="markAllAsRead"
                            class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 transition">
                        <x-ui.icon name="check" class="w-3 h-3" />
                        <span class="hidden sm:inline">{{ __('ui.notifications.mark_all_read') }}</span>
                    </button>
                @endif
            </div>
        </div>

        <div class="space-y-2">
            @forelse($notifications as $notification)
                @php
                    $data = $notification->data ?? [];
                    $message = $data['message'] ?? __('ui.notifications.item_fallback');
                    $date = $notification->created_at?->diffForHumans();
                    $isUnread = is_null($notification->read_at);
                @endphp

                <div class="rounded-xl border p-3 {{ $isUnread ? 'border-brand-200 bg-brand-50/40' : 'border-slate-200/70 bg-white' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900">
                                @if($isUnread)
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-brand-600 mr-1 align-middle"></span>
                                @endif
                                {{ $message }}
                            </p>

                            @if(!empty($data['rdv_id']))
                                <p class="mt-1 inline-flex items-center gap-1 text-xs text-slate-500">
                                    <x-ui.icon name="briefcase" class="w-3 h-3" />
                                    {{ __('ui.notifications.booking_prefix') }} #{{ $data['rdv_id'] }}
                                </p>
                            @endif

                            <p class="mt-1 inline-flex items-center gap-1 text-xs text-slate-400">
                                <x-ui.icon name="clock" class="w-3 h-3" />
                                {{ $date }}
                            </p>
                        </div>

                        <div class="flex items-center gap-1 shrink-0">
                            @if($isUnread)
                                <button wire:click="markAsRead('{{ $notification->id }}')" title="{{ __('ui.notifications.mark_read') }}"
                                        class="inline-flex items-center justify-center rounded-md bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 p-1.5 text-emerald-700 transition">
                                    <x-ui.icon name="check" class="w-3.5 h-3.5" />
                                </button>
                            @endif

                            <button wire:click="deleteNotification('{{ $notification->id }}')" title="{{ __('ui.notifications.delete') }}"
                                    class="inline-flex items-center justify-center rounded-md bg-rose-50 hover:bg-rose-100 border border-rose-200 p-1.5 text-rose-700 transition">
                                <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-6">
                    <div class="grid h-10 w-10 mx-auto place-items-center rounded-xl bg-slate-100 text-slate-400 mb-2">
                        <x-ui.icon name="bell" class="w-5 h-5" />
                    </div>
                    <p class="text-sm text-slate-500">{{ __('ui.notifications.none') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
