@props(['code'])
<x-app-layout :title="__('app.errors.'.$code.'.title')" robots="noindex,nofollow">
    <div class="mx-auto flex max-w-2xl flex-col items-center px-4 py-16 text-center sm:py-24">
        <p class="text-6xl font-bold text-brand-600" dir="ltr">{{ $code }}</p>
        <h1 class="mt-4 text-2xl font-bold text-slate-900">{{ __('app.errors.'.$code.'.title') }}</h1>
        <p class="mt-3 leading-7 text-slate-600">{{ __('app.errors.'.$code.'.message') }}</p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <x-button :href="route('home')">{{ __('app.errors.back_home') }}</x-button>
            {{ $slot }}
        </div>
    </div>
</x-app-layout>
