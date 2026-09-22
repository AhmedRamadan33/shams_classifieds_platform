<x-app-layout :title="__('app.saved_searches.title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('app.saved_searches.title') }}</h1>

        <div class="mt-6">
            @if ($searches->isEmpty())
                <x-empty-state :title="__('app.saved_searches.empty_title')" :message="__('app.saved_searches.empty_message')">
                    <x-button :href="route('search')">{{ __('app.browse.browse_all') }}</x-button>
                </x-empty-state>
            @else
                <ul class="space-y-3">
                    @foreach ($searches as $search)
                        <li class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ $search->url() }}" class="truncate font-bold text-slate-900 hover:text-brand-700 hover:underline">{{ $search->name }}</a>
                                    <p class="mt-0.5 text-sm text-slate-600">{{ __('app.saved_searches.results_count', ['count' => number_format($search->current_count)]) }}</p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('saved-searches.update', $search) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="notify" value="{{ $search->notify ? '0' : '1' }}">
                                        <button type="submit"
                                                class="rounded-lg border px-3 py-1.5 text-xs font-bold {{ $search->notify ? 'border-brand-700 bg-brand-50 text-brand-800' : 'border-slate-300 text-slate-600' }}">
                                            {{ $search->notify ? __('app.saved_searches.notify_on') : __('app.saved_searches.notify_off') }}
                                        </button>
                                    </form>

                                    <x-button :href="$search->url()" variant="secondary" size="sm">{{ __('app.saved_searches.view') }}</x-button>

                                    <form method="POST" action="{{ route('saved-searches.destroy', $search) }}"
                                          x-data @submit="if (! confirm(@js(__('app.saved_searches.confirm_delete')))) $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="ghost" size="sm" class="text-red-700 hover:bg-red-50">{{ __('app.saved_searches.delete') }}</x-button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
