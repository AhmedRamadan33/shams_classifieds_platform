@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'ltr' => false,
])
@php
    // "fields[brand]" -> "fields.brand" so validation errors are found for array style names.
    $errorKey = rtrim(preg_replace('/\[(.*?)\]/', '.$1', $name), '.');
    $id = $attributes->get('id', str_replace(['[', ']', '.'], ['_', '', '_'], $name));
    $hasError = $errors->has($errorKey);
    $current = $type === 'password' ? null : old($errorKey, $value);
@endphp
<div>
    @if ($label)
        <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-slate-700">
            {{ $label }}@if ($required)<span class="ms-1 text-red-600" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ $current }}" @endif
        @required($required)
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('id')->class([
            'block w-full rounded-lg border bg-white px-3 py-2.5 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-600 focus:ring-brand-600',
            'border-slate-300' => ! $hasError,
            'border-red-500' => $hasError,
            'ltr-input' => $ltr,
        ]) }}
    >

    @if ($hint && ! $hasError)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @if ($hasError)
        <p id="{{ $id }}-error" class="mt-1 text-sm text-red-700">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
