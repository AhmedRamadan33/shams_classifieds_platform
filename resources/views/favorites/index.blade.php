<x-app-layout :title="__('app.favorites.title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('app.favorites.title') }}</h1>

        <div class="mt-6">
            @if ($listings->isEmpty())
                <x-empty-state :title="__('app.favorites.empty_title')" :message="__('app.favorites.empty_message')">
                    <x-button :href="route('search')">{{ __('app.browse.browse_all') }}</x-button>
                </x-empty-state>
            @else
                <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($listings as $listing)
                        <div class="flex flex-col gap-2">
                            <x-listing-card :listing="$listing" />

                            <form method="POST" action="{{ route('listings.favorite', $listing) }}">
                                @csrf
                                <input type="hidden" name="favorite" value="0">
                                <button type="submit" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('app.favorites.remove') }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    <x-pagination :paginator="$listings" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
