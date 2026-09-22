<x-guest-layout :title="__('app.auth.forgot_title')">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('app.auth.forgot_title') }}</h1>
    <p class="mt-1 text-sm leading-6 text-slate-600">{{ __('app.auth.forgot_subtitle') }}</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf

        <x-input name="phone" type="tel" :label="__('app.auth.phone')" :hint="__('app.auth.phone_hint')"
                 inputmode="tel" autocomplete="tel" placeholder="01012345678" ltr required autofocus />

        <x-button type="submit" size="lg" class="w-full">{{ __('app.auth.send_code') }}</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600">
        <a href="{{ route('login') }}" class="font-bold text-brand-700 hover:underline">{{ __('app.auth.back_to_login') }}</a>
    </p>
</x-guest-layout>
