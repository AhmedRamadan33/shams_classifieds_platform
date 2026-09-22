@php($active = $store->isActive())
<x-app-layout :title="$store->name">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <x-breadcrumbs :items="[
            ['label' => __('app.nav.home'), 'url' => route('home')],
            ['label' => $store->name, 'url' => null],
        ]" />

        <header class="mt-4 flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 text-xl font-bold text-brand-800">{{ mb_substr($store->name, 0, 1) }}</span>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-bold text-slate-900">{{ $store->name }}</h1>
                    @if ($active)
                        <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-bold text-brand-800">{{ __('app.stores.active_badge') }}</span>
                    @endif
                </div>
                @if ($store->bio)
                    <p class="mt-1 text-sm text-slate-600">{{ $store->bio }}</p>
                @endif
                <p class="mt-1 text-sm text-slate-600">
                    {{ __('app.seller.member_since', ['date' => $store->user->created_at->translatedFormat('F Y')]) }}
                    · {{ __('app.stores.listings_count', ['count' => number_format($listings->total())]) }}
                </p>
            </div>
        </header>

        <div class="mt-6">
            @if ($listings->isEmpty())
                <x-empty-state :title="__('app.stores.empty')" />
            @else
                <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($listings as $listing)
                        <x-listing-card :listing="$listing" />
                    @endforeach
                </div>

                <div class="mt-8">
                    <x-pagination :paginator="$listings" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
