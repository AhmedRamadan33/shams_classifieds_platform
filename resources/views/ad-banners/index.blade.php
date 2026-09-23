@php
    $purchasable = [\App\Enums\AdBannerStatus::Approved, \App\Enums\AdBannerStatus::Active, \App\Enums\AdBannerStatus::Expired];
@endphp
<x-app-layout :title="__('app.ad_banners.index_title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-slate-900">{{ __('app.ad_banners.index_title') }}</h1>
            <x-button :href="route('ad-banners.create')">{{ __('app.ad_banners.create_title') }}</x-button>
        </div>

        @if (session('success'))
            <x-alert type="success" class="mt-4">{{ session('success') }}</x-alert>
        @endif

        <div class="mt-6 space-y-4">
            @forelse ($banners as $banner)
                <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4">
                    <img src="{{ $banner->imageUrl() }}" alt="" class="h-16 w-28 shrink-0 rounded-lg object-cover">

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold text-slate-900">{{ $banner->title ?? $banner->placement->label() }}</p>
                        <p class="mt-0.5 text-sm text-slate-600">{{ $banner->placement->label() }}</p>
                        @if ($banner->status === \App\Enums\AdBannerStatus::Rejected && $banner->rejection_reason)
                            <p class="mt-1 text-sm text-red-700">{{ $banner->rejection_reason }}</p>
                        @endif
                        @if ($banner->expires_at)
                            <p class="mt-1 text-xs text-slate-500">{{ __('app.ad_banners.expires_at', ['date' => $banner->expires_at->translatedFormat('j F Y')]) }}</p>
                        @endif
                    </div>

                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $banner->status->badgeClasses() }}">{{ $banner->status->label() }}</span>

                    @if (in_array($banner->status, $purchasable, true))
                        <x-button :href="route('ad-banners.purchase', $banner)" size="sm">{{ __('app.ad_banners.pay_now') }}</x-button>
                    @endif
                </div>
            @empty
                <x-empty-state :title="__('app.ad_banners.empty_title')" :message="__('app.ad_banners.empty_message')">
                    <x-button :href="route('ad-banners.create')">{{ __('app.ad_banners.create_title') }}</x-button>
                </x-empty-state>
            @endforelse
        </div>

        <div class="mt-8">
            <x-pagination :paginator="$banners" />
        </div>
    </div>
</x-app-layout>
