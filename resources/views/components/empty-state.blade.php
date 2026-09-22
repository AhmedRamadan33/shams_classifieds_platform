@props(['title', 'message' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center']) }}>
    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-600" aria-hidden="true">
        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
    </span>
    <h2 class="mt-4 text-lg font-bold text-slate-900">{{ $title }}</h2>
    @if ($message)
        <p class="mt-2 max-w-md text-sm leading-6 text-slate-600">{{ $message }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">{{ $slot }}</div>
    @endif
</div>
