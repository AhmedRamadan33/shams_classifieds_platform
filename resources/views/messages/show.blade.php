@php($me = auth()->user())
<x-app-layout :title="$other->name" robots="noindex,nofollow">
    <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 lg:px-8">
        <a href="{{ route('messages.index') }}" class="text-sm font-medium text-brand-700 hover:underline">&rarr; {{ __('app.messages.back_to_inbox') }}</a>

        <div class="mt-3 flex items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-100 text-base font-bold text-brand-800">{{ mb_substr($other->name, 0, 1) }}</span>
            <div class="min-w-0">
                <h1 class="truncate text-lg font-bold text-slate-900">{{ $other->name }}</h1>
                <a href="{{ $conversation->listing->url() }}" class="truncate text-sm text-slate-600 hover:text-brand-700 hover:underline">{{ __('app.messages.about_listing', ['title' => $conversation->listing->title]) }}</a>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white"
             x-data="messageThread({
                 pollUrl: @js(route('messages.poll', $conversation)),
                 storeUrl: @js(route('messages.store', $conversation)),
                 lastId: @js($messages->last()?->id ?? 0),
             })">

            <div data-messages class="max-h-[28rem] min-h-[16rem] overflow-y-auto p-4">
                <div x-ref="list" class="space-y-3">
                    @forelse ($messages as $message)
                        <div class="flex {{ $message->sender_id === $me->id ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[80%] rounded-2xl px-4 py-2 text-sm {{ $message->sender_id === $me->id ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-900' }}">
                                <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                                <p class="mt-1 text-xs opacity-70">{{ $message->created_at->translatedFormat('H:i') }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="py-10 text-center text-sm text-slate-500">{{ __('app.messages.empty_thread') }}</p>
                    @endforelse
                </div>
            </div>

            <form @submit.prevent="send()" class="flex items-end gap-2 border-t border-slate-200 p-3">
                <label for="message-body" class="sr-only">{{ __('app.messages.placeholder') }}</label>
                <textarea id="message-body" x-model="body" rows="1" maxlength="2000"
                          placeholder="{{ __('app.messages.placeholder') }}"
                          class="block w-full resize-none rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600"
                          @keydown.enter.prevent="send()"></textarea>
                <x-button type="submit" x-bind:disabled="sending">{{ __('app.messages.send') }}</x-button>
            </form>
            <p x-show="error" x-cloak role="alert" class="px-3 pb-3 text-sm text-red-700" x-text="error"></p>
        </div>
    </div>
</x-app-layout>
