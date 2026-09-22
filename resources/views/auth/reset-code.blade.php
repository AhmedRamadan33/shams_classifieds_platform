<x-guest-layout :title="__('app.auth.verify_title')">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('app.auth.verify_title') }}</h1>
    <p class="mt-1 text-sm leading-6 text-slate-600">
        {{ __('app.auth.verify_subtitle', ['length' => config('classifieds.otp.length')]) }}
        <span dir="ltr" class="inline-block font-bold text-slate-900">{{ $phone }}</span>
    </p>

    <form method="POST" action="{{ route('password.verify.store') }}" class="mt-6 space-y-4">
        @csrf

        <x-input name="code" :label="__('app.auth.code')" inputmode="numeric" autocomplete="one-time-code"
                 :maxlength="config('classifieds.otp.length')" placeholder="------" class="text-center text-xl tracking-[0.5em]" ltr required autofocus />

        <x-button type="submit" size="lg" class="w-full">{{ __('app.auth.verify_button') }}</x-button>
    </form>

    <form method="POST" action="{{ route('password.resend') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-brand-700 hover:underline">{{ __('app.auth.resend') }}</button>
    </form>
</x-guest-layout>
