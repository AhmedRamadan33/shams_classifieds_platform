@props(['placement'])

@php
    $banners = \App\Models\AdBanner::query()
        ->currentlyActive()
        ->forPlacement(\App\Enums\AdPlacement::from($placement))
        ->with('media')
        ->inRandomOrder()
        ->limit(10)
        ->get();
    $count = $banners->count();
    $slides = $count > 1;
@endphp

@if ($count > 0)
    <div
        class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white"
        role="region"
        aria-roledescription="carousel"
        aria-label="{{ __('app.ad_banners.sponsored_label') }}"
        @if ($slides)
            x-data="{
                index: 0,
                count: {{ $count }},
                timer: null,
                start() {
                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    this.stop();
                    this.timer = setInterval(() => this.next(), 5000);
                },
                stop() { clearInterval(this.timer); this.timer = null; },
                next() { this.index = (this.index + 1) % this.count; },
                prev() { this.index = (this.index - 1 + this.count) % this.count; },
            }"
            x-init="start()"
            @mouseenter="stop()"
            @mouseleave="start()"
            @focusin="stop()"
            @focusout="start()"
        @endif
    >
        <span class="absolute start-2 top-2 z-20 rounded-full bg-slate-900/70 px-2 py-0.5 text-xs font-medium text-white">{{ __('app.ad_banners.sponsored_label') }}</span>

        <div class="relative aspect-[16/5] w-full bg-slate-100">
            @foreach ($banners as $i => $banner)
                <a
                    href="{{ route('ad-banners.click', $banner) }}"
                    @unless ($banner->targetsListing()) target="_blank" @endunless
                    rel="sponsored noopener"
                    role="group"
                    aria-roledescription="slide"
                    aria-label="{{ ($i + 1).' / '.$count }}"
                    class="absolute inset-0 block"
                    @if ($slides)
                        x-show="index === {{ $i }}"
                        x-transition.opacity.duration.500ms
                        @if ($i > 0) style="display: none" @endif
                    @endif
                >
                    <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title ?? __('app.ad_banners.sponsored_label') }}" class="h-full w-full object-cover" @if ($i > 0) loading="lazy" @endif>
                </a>
            @endforeach
        </div>

        @if ($slides)
            <button type="button" @click="next()" class="absolute start-2 top-1/2 z-20 -translate-y-1/2 rounded-full bg-white/80 p-1.5 text-slate-800 shadow hover:bg-white" aria-label="{{ __('app.ad_banners.next') }}">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.7 5.3a1 1 0 010 1.4L9.4 10l3.3 3.3a1 1 0 01-1.4 1.4l-4-4a1 1 0 010-1.4l4-4a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
            </button>
            <button type="button" @click="prev()" class="absolute end-2 top-1/2 z-20 -translate-y-1/2 rounded-full bg-white/80 p-1.5 text-slate-800 shadow hover:bg-white" aria-label="{{ __('app.ad_banners.previous') }}">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.3 14.7a1 1 0 010-1.4L10.6 10 7.3 6.7a1 1 0 011.4-1.4l4 4a1 1 0 010 1.4l-4 4a1 1 0 01-1.4 0z" clip-rule="evenodd"/></svg>
            </button>
            <div class="absolute inset-x-0 bottom-2 z-20 flex justify-center gap-1.5">
                @foreach ($banners as $i => $banner)
                    <button type="button" @click="index = {{ $i }}" class="h-2 w-2 rounded-full transition" :class="index === {{ $i }} ? 'w-5 bg-white' : 'bg-white/60 hover:bg-white/90'" aria-label="{{ ($i + 1).' / '.$count }}"></button>
                @endforeach
            </div>
        @endif
    </div>
@endif
