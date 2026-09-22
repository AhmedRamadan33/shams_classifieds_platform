@php($me = auth()->user())
<x-app-layout :title="__('app.messages.title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('app.messages.title') }}</h1>

        <div class="mt-6">
            @if ($conversations->isEmpty())
                <x-empty-state :title="__('app.messages.empty_title')" :message="__('app.messages.empty_message')" />
            @else
                <ul class="divide-y divide-slate-200 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    @foreach ($conversations as $conversation)
                        @php($other = $conversation->otherParticipant($me))
                        <li>
                            <a href="{{ route('messages.show', $conversation) }}" class="flex items-center gap-3 p-4 hover:bg-slate-50">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-100 text-base font-bold text-brand-800">{{ mb_substr($other->name, 0, 1) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="truncate font-bold text-slate-900">{{ $other->name }}</p>
                                        @if ($conversation->last_message_at)
                                            <span class="shrink-0 text-xs text-slate-500">{{ $conversation->last_message_at->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                    <p class="truncate text-sm text-slate-600">{{ __('app.messages.about_listing', ['title' => $conversation->listing->title]) }}</p>
                                </div>
                                @if ($conversation->unread_count > 0)
                                    <span class="flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full bg-brand-700 px-1.5 text-xs font-bold text-white">{{ $conversation->unread_count > 9 ? '9+' : $conversation->unread_count }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-8">
                    <x-pagination :paginator="$conversations" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
