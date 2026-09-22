<x-guest-layout :title="__('app.auth.register_title')">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('app.auth.register_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('app.auth.register_subtitle') }}</p>

    <form method="POST" action="{{ route('register') }}" class="relative mt-6 space-y-4">
        @csrf
        <x-honeypot />

        <x-input name="name" :label="__('app.auth.name')" autocomplete="name" required autofocus />

        <x-input name="phone" type="tel" :label="__('app.auth.phone')" :hint="__('app.auth.phone_hint')"
                 inputmode="tel" autocomplete="tel" placeholder="01012345678" ltr required />

        <x-input name="password" type="password" :label="__('app.auth.password')" :hint="__('app.auth.password_hint')"
                 autocomplete="new-password" required />

        <x-input name="password_confirmation" type="password" :label="__('app.auth.password_confirmation')"
                 autocomplete="new-password" required />

        <x-captcha />

        <x-button type="submit" size="lg" class="w-full">{{ __('app.auth.register_button') }}</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600">
        {{ __('app.auth.have_account') }}
        <a href="{{ route('login') }}" class="font-bold text-brand-700 hover:underline">{{ __('app.auth.login_title') }}</a>
    </p>
</x-guest-layout>
