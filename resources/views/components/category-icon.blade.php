@props(['name' => null])
<svg {{ $attributes->merge(['class' => 'h-6 w-6']) }} fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="{{ \App\Support\CategoryIcons::path($name) }}"/>
</svg>
