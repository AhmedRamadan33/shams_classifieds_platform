<x-app-layout>
    <section class="bg-gradient-to-b from-brand-50 to-slate-50">
        <div class="mx-auto max-w-4xl px-4 py-12 text-center sm:px-6 sm:py-16 lg:px-8">
            <h1 class="text-3xl font-bold leading-tight text-slate-900 sm:text-4xl">{{ __('app.home.hero_title') }}</h1>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600">{{ __('app.home.hero_subtitle') }}</p>

            <form method="GET" action="{{ route('search') }}" role="search" class="mx-auto mt-8 flex max-w-2xl gap-2">
                <label for="home-q" class="sr-only">{{ __('app.browse.search_button') }}</label>
                <input id="home-q" type="search" name="q" placeholder="{{ __('app.browse.search_placeholder') }}" autocomplete="off"
                       class="block w-full rounded-xl border border-slate-300 bg-white px-5 py-3.5 text-base shadow-sm focus:border-brand-600 focus:ring-brand-600">
                <x-button type="submit" size="lg">{{ __('app.browse.search_button') }}</x-button>
            </form>
        </div>
    </section>

    <div class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-ad-banner placement="home_top" />
    </div>

    <div class="mx-auto max-w-7xl space-y-12 px-4 pb-4 sm:px-6 lg:px-8">
        <section aria-labelledby="home-categories" class="-mt-2">
            <h2 id="home-categories" class="text-xl font-bold text-slate-900">{{ __('app.home.categories_title') }}</h2>
            <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('categories.show', $category->slug) }}"
                           class="flex h-full flex-col items-center gap-2 rounded-xl border border-slate-200 bg-white p-4 text-center text-sm font-medium text-slate-800 transition hover:border-brand-500 hover:bg-brand-50">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                                <x-category-icon :name="$category->icon" class="h-6 w-6" />
                            </span>
                            {{ $category->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        @if ($featured->isNotEmpty())
            <section aria-labelledby="home-featured">
                <div class="flex items-center justify-between">
                    <h2 id="home-featured" class="text-xl font-bold text-slate-900">{{ __('app.home.featured_title') }}</h2>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($featured as $listing)
                        <x-listing-card :listing="$listing" />
                    @endforeach
                </div>
            </section>
        @endif

        <section aria-labelledby="home-latest">
            <div class="flex items-center justify-between">
                <h2 id="home-latest" class="text-xl font-bold text-slate-900">{{ __('app.home.latest_title') }}</h2>
                <a href="{{ route('search') }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('app.home.view_all') }}</a>
            </div>

            @if ($latest->isEmpty())
                <x-empty-state class="mt-4" :title="__('app.home.no_listings')">
                    <x-button :href="route('listings.create')">{{ __('app.nav.add_listing') }}</x-button>
                </x-empty-state>
            @else
                <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($latest as $listing)
                        <x-listing-card :listing="$listing" />
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-2xl bg-brand-700 px-6 py-8 text-center text-white sm:py-10">
            <h2 class="text-2xl font-bold">{{ __('app.home.cta_title') }}</h2>
            <p class="mx-auto mt-2 max-w-xl text-brand-50">{{ __('app.home.cta_text') }}</p>
            <a href="{{ route('listings.create') }}" class="mt-5 inline-flex rounded-lg bg-white px-6 py-3 text-sm font-bold text-brand-800 hover:bg-brand-50">{{ __('app.nav.add_listing') }}</a>
        </section>
    </div>
</x-app-layout>
