<x-app-layout :title="__('app.notifications.title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-slate-900">{{ __('app.notifications.title') }}</h1>
        </div>

        <div class="mt-6">
            @if ($notifications->isEmpty())
                <x-empty-state :title="__('app.notifications.empty_title')" :message="__('app.notifications.empty_message')" />
            @else
                <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    @foreach ($notifications as $notification)
                        @php($unread = isset($unreadIds[$notification->id]))
                        <li class="{{ $unread ? 'bg-brand-50' : '' }}">
                            <a href="{{ $notification->data['url'] ?? route('dashboard') }}" class="flex items-start gap-3 px-4 py-4 hover:bg-slate-50">
                                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $unread ? 'bg-brand-600' : 'bg-transparent' }}" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm leading-6 text-slate-900">{{ $notification->data['message'] ?? '' }}</span>
                                    <time datetime="{{ $notification->created_at->toIso8601String() }}" class="mt-1 block text-xs text-slate-500">{{ $notification->created_at->diffForHumans() }}</time>
                                </span>
                                @if ($unread)
                                    <span class="shrink-0 rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-800">{{ __('app.notifications.unread') }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-6">
                    <x-pagination :paginator="$notifications" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
