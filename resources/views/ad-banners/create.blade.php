<x-app-layout :title="__('app.ad_banners.create_title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('app.ad_banners.create_title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('app.ad_banners.create_subtitle') }}</p>

        <form method="POST" action="{{ route('ad-banners.store') }}" enctype="multipart/form-data"
              x-data="{ type: '{{ old('target_type', 'url') }}' }"
              class="mt-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <x-honeypot />

            <x-select name="placement" :label="__('app.ad_banners.placement')" :options="collect($placements)->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all()"
                      :hint="__('app.ad_banners.placement_help')" required />

            <x-input name="title" :label="__('app.ad_banners.internal_title')" :hint="__('app.ad_banners.internal_title_help')" maxlength="150" />

            <fieldset class="space-y-2">
                <legend class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.ad_banners.target_type') }}</legend>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" name="target_type" value="url" x-model="type" class="text-brand-700 focus:ring-brand-600">
                    {{ __('app.ad_banners.target_external') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700 {{ $listings->isEmpty() ? 'opacity-60' : '' }}">
                    <input type="radio" name="target_type" value="listing" x-model="type" @disabled($listings->isEmpty()) class="text-brand-700 focus:ring-brand-600">
                    {{ __('app.ad_banners.target_listing') }}
                </label>
                @error('target_type')
                    <p class="text-sm text-red-700">{{ $message }}</p>
                @enderror
            </fieldset>

            <div x-show="type === 'url'">
                <x-input name="target_url" type="url" :label="__('app.ad_banners.target_url')" ltr maxlength="500" placeholder="https://" />
            </div>

            <div x-show="type === 'listing'" x-cloak>
                @if ($listings->isEmpty())
                    <x-alert type="warning">{{ __('app.ad_banners.no_live_listings') }}</x-alert>
                @else
                    <x-select name="listing_id" :label="__('app.ad_banners.listing')" :options="$listings->pluck('title', 'id')->all()"
                              :placeholder="__('app.ad_banners.choose_listing')" :hint="__('app.ad_banners.listing_help')" />
                @endif
            </div>

            <x-input name="image" type="file" :label="__('app.ad_banners.image')"
                     :hint="__('app.ad_banners.image_help', ['size' => number_format(config('classifieds.max_image_kb') / 1024, 1).' MB'])"
                     accept="image/jpeg,image/png,image/webp" required />

            <x-captcha />

            <x-button type="submit">{{ __('app.ad_banners.submit') }}</x-button>
        </form>
    </div>
</x-app-layout>
