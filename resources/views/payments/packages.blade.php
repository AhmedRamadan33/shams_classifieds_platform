<x-app-layout :title="__('app.payments.feature_title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('app.payments.feature_title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('app.payments.feature_subtitle', ['title' => $listing->title]) }}</p>

        @if ($listing->isFeatured())
            <x-alert type="info" class="mt-4">{{ __('app.my_listings.featured_until', ['date' => $listing->featured_until->translatedFormat('j F Y')]) }}</x-alert>
        @endif

        @if ($packages->isEmpty())
            <x-alert type="warning" class="mt-6">{{ __('app.payments.no_packages') }}</x-alert>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                @foreach ($packages as $package)
                    <form method="POST" action="{{ route('listings.feature.store', $listing) }}"
                          class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm">
                        @csrf
                        <input type="hidden" name="package_id" value="{{ $package->id }}">
                        <span class="text-sm font-bold text-slate-500">{{ __('app.payments.days', ['count' => $package->days]) }}</span>
                        <span class="mt-2 text-2xl font-bold text-brand-700">{{ $package->formattedPrice() }}</span>
                        <span class="mt-1 text-sm text-slate-600">{{ $package->name }}</span>
                        <x-button type="submit" class="mt-4">{{ __('app.payments.choose') }}</x-button>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
