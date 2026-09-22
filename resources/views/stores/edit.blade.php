<x-app-layout :title="$store ? __('app.stores.edit_title') : __('app.stores.create_title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">{{ $store ? __('app.stores.edit_title') : __('app.stores.create_title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('app.stores.subtitle') }}</p>

        @if ($store)
            @if ($store->isActive())
                <x-alert type="success" class="mt-4">{{ __('app.stores.active_badge') }}</x-alert>
            @else
                <x-alert type="warning" class="mt-4">
                    {{ __('app.stores.inactive_notice') }}
                    <a href="{{ route('subscribe') }}" class="font-bold underline">{{ __('app.stores.subscribe_cta') }}</a>
                </x-alert>
            @endif
        @endif

        <form method="POST" action="{{ route('store.save') }}" class="mt-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <x-input name="name" :label="__('app.stores.name')" :value="$store?->name" required />
            <x-input name="slug" :label="__('app.stores.slug')" :hint="__('app.stores.slug_help')" :value="$store?->slug" ltr required />
            <x-textarea name="bio" :label="__('app.stores.bio')" :value="$store?->bio" rows="4" />
            <x-button type="submit">{{ __('app.stores.save') }}</x-button>

            @if ($store)
                <a href="{{ $store->url() }}" class="ms-3 text-sm font-medium text-brand-700 hover:underline">{{ __('app.stores.view_public') }}</a>
            @endif
        </form>
    </div>
</x-app-layout>
