@php
    $crumbs = [['label' => __('app.nav.home'), 'url' => route('home')]];

    if ($isSearch) {
        $crumbs[] = ['label' => $heading, 'url' => null];
    } else {
        foreach ($ancestors as $node) {
            $crumbs[] = ['label' => $node->name, 'url' => route('categories.show', $node->slug)];
        }
        if ($governorate) {
            $crumbs[count($crumbs) - 1]['url'] = route('categories.show', $category->slug);
            $crumbs[] = ['label' => $governorate->name, 'url' => null];
        }
    }

    $clearUrl = $isSearch ? route('search', array_filter(['q' => $q ?? null, 'category' => $category?->slug])) : route('categories.show', $category->slug);
    $currentQuery = request()->query();

    $page = $listings->currentPage();
    $pageTitle = $page > 1 ? $heading.' — '.__('app.browse.page_n', ['page' => $page]) : $heading;
    $breadcrumbLd = \App\Support\StructuredData::breadcrumbs(
        collect($crumbs)->map(fn ($crumb, $i) => $i === count($crumbs) - 1 ? ['label' => $crumb['label'], 'url' => $seo['canonical']] : $crumb)->all(),
    );
@endphp
<x-app-layout :title="$pageTitle"
              :description="__('app.browse.results_count', ['count' => $listings->total()]).' — '.$heading"
              :canonical="$seo['canonical']"
              :robots="$seo['robots']"
              :json-ld="$isSearch ? [] : [$breadcrumbLd]">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <x-breadcrumbs :items="$crumbs" />

        <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ $heading }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('app.browse.results_count', ['count' => number_format($listings->total())]) }}</p>
            </div>

            {{-- Quick sort: keeps every current filter as hidden inputs --}}
            <form method="GET" action="{{ $formAction }}" class="flex items-center gap-2 text-sm">
                @foreach (\App\Support\QueryFields::flatten($currentQuery, ['sort', 'page']) as [$name, $value])
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <label for="sort" class="text-slate-600">{{ __('app.browse.sort_by') }}</label>
                <select id="sort" name="sort" onchange="this.form.submit()" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                    @foreach (\App\Queries\ListingSearch::SORTS as $sort)
                        <option value="{{ $sort }}" @selected($search->sort() === $sort)>{{ __('app.browse.sorts.'.$sort) }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="rounded-lg border border-slate-300 px-3 py-2">{{ __('app.browse.apply_filters') }}</button></noscript>
            </form>
        </div>

        @auth
            <div class="mt-3" x-data="{ open: {{ $errors->has('name') ? 'true' : 'false' }} }">
                <button type="button" @click="open = ! open" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:underline">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/></svg>
                    {{ __('app.browse.save_search') }}
                </button>

                <form x-show="open" x-cloak method="POST" action="{{ route('saved-searches.store') }}"
                      class="mt-2 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
                    @csrf
                    @if ($category)
                        <input type="hidden" name="category_slug" value="{{ $category->slug }}">
                    @endif
                    @if ($governorate)
                        <input type="hidden" name="governorate_slug" value="{{ $governorate->slug }}">
                    @endif
                    @foreach (\App\Support\QueryFields::flatten($currentQuery, ['page']) as [$name, $value])
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach

                    <div class="min-w-[14rem] flex-1">
                        <label for="save-search-name" class="mb-1 block text-xs font-medium text-slate-700">{{ __('app.browse.save_search_name') }}</label>
                        <input id="save-search-name" type="text" name="name" maxlength="100" required
                               placeholder="{{ __('app.browse.save_search_name_placeholder') }}"
                               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="notify" value="1" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                        {{ __('app.browse.save_search_notify') }}
                    </label>

                    <x-button type="submit" size="sm">{{ __('app.browse.save_search_button') }}</x-button>
                </form>
                @error('name')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>
        @endauth

        @if ($isSearch)
            <form method="GET" action="{{ route('search') }}" role="search" class="mt-4 flex gap-2">
                @if ($category)
                    <input type="hidden" name="category" value="{{ $category->slug }}">
                @endif
                <label for="results-q" class="sr-only">{{ __('app.browse.search_button') }}</label>
                <input id="results-q" type="search" name="q" value="{{ $q ?? '' }}" placeholder="{{ __('app.browse.search_placeholder') }}"
                       class="block w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600">
                <x-button type="submit">{{ __('app.browse.search_button') }}</x-button>
            </form>
        @endif

        @if ($children->isNotEmpty())
            <nav class="mt-4" aria-label="{{ __('app.browse.subcategories') }}">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($children as $child)
                        <li><a href="{{ route('categories.show', $child->slug) }}" class="inline-block rounded-full border border-slate-300 bg-white px-4 py-1.5 text-sm text-slate-700 hover:border-brand-600 hover:text-brand-700">{{ $child->name }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <x-filters :action="$formAction" :search="$search" :hidden="$hidden" :clear-url="$clearUrl"
                       :selected-governorate="$governorate?->slug" />

            <section aria-label="{{ $heading }}">
                @if ($listings->isEmpty())
                    <x-empty-state :title="__('app.browse.empty_title')" :message="__('app.browse.empty_message')">
                        <x-button :href="$clearUrl" variant="secondary">{{ __('app.browse.clear_filters') }}</x-button>
                        <x-button :href="route('search')" variant="secondary">{{ __('app.browse.browse_all') }}</x-button>
                        <x-button :href="route('listings.create')">{{ __('app.nav.add_listing') }}</x-button>
                    </x-empty-state>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
                        @foreach ($listings as $listing)
                            <x-listing-card :listing="$listing" />
                        @endforeach
                    </div>

                    <div class="mt-8">
                        <x-pagination :paginator="$listings" />
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
