<x-app-layout :title="__('app.subscriptions.title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('app.subscriptions.title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('app.subscriptions.subtitle') }}</p>

        @if ($subscription)
            <x-alert type="info" class="mt-4">
                {{ __('app.subscriptions.current', ['plan' => $subscription->plan->name, 'date' => $subscription->expires_at->translatedFormat('j F Y')]) }}
            </x-alert>
        @endif

        @if ($plans->isEmpty())
            <x-alert type="warning" class="mt-6">{{ __('app.subscriptions.no_plans') }}</x-alert>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                @foreach ($plans as $plan)
                    <form method="POST" action="{{ route('subscribe.store') }}"
                          class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                        <span class="text-sm font-bold text-slate-500">{{ __('app.subscriptions.days', ['count' => $plan->duration_days]) }}</span>
                        <span class="mt-2 text-2xl font-bold text-brand-700">{{ $plan->formattedPrice() }}</span>
                        <span class="mt-1 text-sm text-slate-600">{{ $plan->name }}</span>
                        <span class="mt-1 text-xs text-slate-500">{{ __('app.subscriptions.daily_limit', ['count' => $plan->daily_listing_limit]) }}</span>
                        <x-button type="submit" class="mt-4">{{ __('app.subscriptions.choose') }}</x-button>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
