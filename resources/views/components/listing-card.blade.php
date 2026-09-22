@props(['listing'])
@php
    $cover = $listing->coverUrl('thumb');
    $location = $listing->governorate?->name.($listing->city ? ' - '.$listing->city->name : '');
@endphp
<article class="group relative flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
    <a href="{{ $listing->url() }}" class="flex h-full flex-col">
        <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
            @if ($cover)
                <img src="{{ $cover }}" @if ($srcset = $listing->coverSrcset()) srcset="{{ $srcset }}" sizes="(min-width: 1280px) 20vw, (min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw" @endif
                     alt="{{ $listing->title }}" width="400" height="300" loading="lazy" decoding="async"
                     class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
            @else
                <div class="flex h-full w-full flex-col items-center justify-center gap-1 text-slate-400">
                    <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/></svg>
                    <span class="text-xs">{{ __('app.browse.no_image') }}</span>
                </div>
            @endif

            @if ($listing->isFeatured())
                <span class="absolute start-2 top-2 rounded-full bg-amber-400 px-2.5 py-0.5 text-xs font-bold text-amber-950">{{ __('app.browse.featured') }}</span>
            @endif
        </div>

        <div class="flex flex-1 flex-col p-3">
            <h3 class="line-clamp-2 min-h-[2.75rem] text-sm font-bold leading-snug text-slate-900 group-hover:text-brand-700">{{ $listing->title }}</h3>
            <p class="mt-2 text-base font-bold text-brand-700">{{ $listing->formattedPrice() }}</p>
            <div class="mt-auto flex items-center justify-between gap-2 pt-3 text-xs text-slate-500">
                <span class="truncate">{{ $location }}</span>
                @if ($listing->published_at)
                    <time datetime="{{ $listing->published_at->toIso8601String() }}" class="shrink-0">{{ $listing->published_at->diffForHumans() }}</time>
                @endif
            </div>
        </div>
    </a>

    @if ($listing->isPubliclyListed())
        <x-favorite-button :listing="$listing" class="absolute end-2 top-2 z-10" />
    @endif

    {{ $slot }}
</article>
