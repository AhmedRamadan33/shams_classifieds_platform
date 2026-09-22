@props(['rating' => null, 'count' => 0, 'showCount' => true])
@php($rounded = $rating !== null ? (int) round($rating) : 0)
<div class="flex items-center gap-1.5" role="img" aria-label="{{ $rating !== null ? $rating.'/5' : __('app.reviews.no_rating') }}">
    <span class="flex items-center gap-0.5" aria-hidden="true">
        @for ($i = 1; $i <= 5; $i++)
            <svg class="h-4 w-4 {{ $i <= $rounded ? 'fill-amber-400 text-amber-400' : 'fill-slate-200 text-slate-200' }}" viewBox="0 0 20 20" stroke="none">
                <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1 1 5.8L10 14.9l-5.21 2.62 1-5.8-4.21-4.1 5.82-.85L10 1.5z"/>
            </svg>
        @endfor
    </span>
    @if ($rating !== null)
        <span class="text-sm font-bold text-slate-700">{{ $rating }}</span>
        @if ($showCount)
            <span class="text-xs text-slate-500">({{ __('app.reviews.count', ['count' => number_format($count)]) }})</span>
        @endif
    @else
        <span class="text-xs text-slate-500">{{ __('app.reviews.no_rating') }}</span>
    @endif
</div>
