<x-guest-layout :title="__('app.auth.reset_title')">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('app.auth.reset_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('app.auth.reset_subtitle') }}</p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">
        @csrf

        <x-input name="password" type="password" :label="__('app.auth.new_password')" :hint="__('app.auth.password_hint')"
                 autocomplete="new-password" required autofocus />

        <x-input name="password_confirmation" type="password" :label="__('app.auth.password_confirmation')"
                 autocomplete="new-password" required />

        <x-button type="submit" size="lg" class="w-full">{{ __('app.auth.reset_button') }}</x-button>
    </form>
</x-guest-layout>
