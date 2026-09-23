<x-app-layout :title="__('app.ad_banners.create_title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('app.ad_banners.create_title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('app.ad_banners.create_subtitle') }}</p>

        <form method="POST" action="{{ route('ad-banners.store') }}" enctype="multipart/form-data"
              class="mt-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <x-honeypot />

            <x-select name="placement" :label="__('app.ad_banners.placement')" :options="collect($placements)->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all()"
                      :hint="__('app.ad_banners.placement_help')" required />

            <x-input name="title" :label="__('app.ad_banners.internal_title')" :hint="__('app.ad_banners.internal_title_help')" maxlength="150" />

            <x-input name="target_url" type="url" :label="__('app.ad_banners.target_url')" ltr required maxlength="500" placeholder="https://" />

            <x-input name="image" type="file" :label="__('app.ad_banners.image')"
                     :hint="__('app.ad_banners.image_help', ['size' => number_format(config('classifieds.max_image_kb') / 1024, 1).' MB'])"
                     accept="image/jpeg,image/png,image/webp" required />

            <x-captcha />

            <x-button type="submit">{{ __('app.ad_banners.submit') }}</x-button>
        </form>
    </div>
</x-app-layout>
