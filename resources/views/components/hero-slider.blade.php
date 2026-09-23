@php
    $slides = \App\Models\HeroSlide::query()->active()->orderBy('sort_order')->get();
@endphp

@if ($slides->isNotEmpty())
    <section class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8" aria-roledescription="carousel" aria-label="{{ __('app.home.hero_slider') }}"
             x-data="{ index: 0, total: {{ $slides->count() }}, timer: null, start() { this.timer = setInterval(() => this.index = (this.index + 1) % this.total, 6000) } }"
             x-init="start()">
        <div class="relative overflow-hidden rounded-2xl">
            @foreach ($slides as $i => $slide)
                <div x-show="index === {{ $i }}" x-cloak x-transition.opacity.duration.500ms>
                    @if ($slide->link_url)
                        <a href="{{ $slide->link_url }}" class="block">
                            <img src="{{ $slide->imageUrl() }}" alt="{{ $slide->title ?? '' }}" class="aspect-[16/6] w-full object-cover sm:aspect-[21/6]" loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                        </a>
                    @else
                        <img src="{{ $slide->imageUrl() }}" alt="{{ $slide->title ?? '' }}" class="aspect-[16/6] w-full object-cover sm:aspect-[21/6]" loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                    @endif

                    @if ($slide->title || $slide->subtitle)
                        <div class="pointer-events-none absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-slate-900/70 via-slate-900/10 to-transparent p-5 sm:p-8">
                            @if ($slide->title)
                                <p class="text-lg font-bold text-white sm:text-2xl">{{ $slide->title }}</p>
                            @endif
                            @if ($slide->subtitle)
                                <p class="mt-1 max-w-xl text-sm text-white/90 sm:text-base">{{ $slide->subtitle }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach

            @if ($slides->count() > 1)
                <div class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5">
                    @foreach ($slides as $i => $slide)
                        <button type="button" @click="index = {{ $i }}"
                                :class="index === {{ $i }} ? 'bg-white' : 'bg-white/50'"
                                class="h-2 w-2 rounded-full transition"
                                aria-label="{{ __('app.home.hero_slide_n', ['n' => $i + 1]) }}"></button>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
