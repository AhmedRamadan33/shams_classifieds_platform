@props(['items'])
{{-- $items: list of ['label' => string, 'url' => ?string]. The last item is the current page. --}}
<nav aria-label="{{ __('app.breadcrumbs') }}" {{ $attributes->merge(['class' => 'text-sm text-slate-600']) }}>
    <ol class="flex flex-wrap items-center gap-1.5">
        @foreach ($items as $item)
            <li class="flex min-w-0 items-center gap-1.5">
                @unless ($loop->first)
                    <svg class="h-4 w-4 shrink-0 text-slate-400 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                @endunless

                @if (! empty($item['url']) && ! $loop->last)
                    <a href="{{ $item['url'] }}" class="hover:text-brand-700 hover:underline">{{ $item['label'] }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif class="truncate font-medium text-slate-800">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
