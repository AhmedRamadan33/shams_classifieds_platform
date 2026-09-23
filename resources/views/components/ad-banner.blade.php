@props(['placement'])

@php
    $banner = \App\Models\AdBanner::query()->currentlyActive()->forPlacement(\App\Enums\AdPlacement::from($placement))->inRandomOrder()->first();
@endphp

@if ($banner)
    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <span class="absolute start-2 top-2 z-10 rounded-full bg-slate-900/70 px-2 py-0.5 text-xs font-medium text-white">{{ __('app.ad_banners.sponsored_label') }}</span>
        <a href="{{ route('ad-banners.click', $banner) }}" @unless ($banner->targetsListing()) target="_blank" @endunless rel="sponsored noopener" class="block">
            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title ?? __('app.ad_banners.sponsored_label') }}" class="w-full object-cover" loading="lazy">
        </a>
    </div>
@endif
