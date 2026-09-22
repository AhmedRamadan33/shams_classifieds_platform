@php
    $status = $payment->status->value;
@endphp
<x-app-layout :title="__('app.payments.result_title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-md px-4 py-14 text-center sm:px-6 lg:px-8">
        @if ($status === 'paid')
            <x-alert type="success">
                <p class="font-bold">{{ __('app.payments.paid_title') }}</p>
                @if ($payment->isForSubscription())
                    <p class="mt-1">{{ __('app.payments.paid_body_subscription', ['plan' => $payment->subscription->plan->name, 'date' => $payment->subscription->expires_at?->translatedFormat('j F Y')]) }}</p>
                @else
                    <p class="mt-1">{{ __('app.payments.paid_body', ['title' => $payment->listing->title, 'date' => $payment->listing->featured_until?->translatedFormat('j F Y')]) }}</p>
                @endif
            </x-alert>
        @elseif ($status === 'pending')
            <meta http-equiv="refresh" content="5">
            <x-alert type="info">
                <p class="font-bold">{{ __('app.payments.pending_title') }}</p>
                <p class="mt-1">{{ __('app.payments.pending_body') }}</p>
            </x-alert>
        @else
            <x-alert type="error">
                <p class="font-bold">{{ __('app.payments.failed_title') }}</p>
                <p class="mt-1">{{ __('app.payments.failed_body') }}</p>
            </x-alert>
        @endif

        <x-button :href="route('dashboard')" class="mt-6">{{ __('app.payments.back_to_dashboard') }}</x-button>
    </div>
</x-app-layout>
