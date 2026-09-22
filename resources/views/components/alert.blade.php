@props(['type' => 'info'])
@php
    $styles = [
        'success' => 'border-green-200 bg-green-50 text-green-900',
        'error' => 'border-red-200 bg-red-50 text-red-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'info' => 'border-sky-200 bg-sky-50 text-sky-900',
    ];
@endphp
<div role="{{ $type === 'error' ? 'alert' : 'status' }}"
     {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 text-sm leading-6 '.($styles[$type] ?? $styles['info'])]) }}>
    {{ $slot }}
</div>
