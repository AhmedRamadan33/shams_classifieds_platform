<x-guest-layout :title="__('app.auth.login_title')">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('app.auth.login_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('app.auth.login_subtitle') }}</p>

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <x-input name="phone" type="tel" :label="__('app.auth.phone')" :hint="__('app.auth.phone_hint')"
                 inputmode="tel" autocomplete="tel" placeholder="01012345678" ltr required autofocus />

        <x-input name="password" type="password" :label="__('app.auth.password')" autocomplete="current-password" required />

        <div class="flex items-center justify-between gap-3">
            <label for="remember" class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input id="remember" type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                {{ __('app.auth.remember') }}
            </label>
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('app.auth.forgot') }}</a>
        </div>

        <x-button type="submit" size="lg" class="w-full">{{ __('app.auth.login_title') }}</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600">
        {{ __('app.auth.no_account') }}
        <a href="{{ route('register') }}" class="font-bold text-brand-700 hover:underline">{{ __('app.auth.register_title') }}</a>
    </p>
</x-guest-layout>
