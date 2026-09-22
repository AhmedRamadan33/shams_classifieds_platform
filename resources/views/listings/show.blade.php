@php
    $crumbs = [['label' => __('app.nav.home'), 'url' => route('home')]];
    foreach ($ancestors as $node) {
        $crumbs[] = ['label' => $node->name, 'url' => route('categories.show', $node->slug)];
    }
    $crumbs[] = ['label' => $listing->title, 'url' => null];

    $location = $listing->governorate->name.($listing->city ? ' - '.$listing->city->name : '');
    $seller = $listing->user;
    $bannerKey = match (true) {
        (bool) $seller->is_banned => 'banned',
        $listing->isExpired() => 'expired',
        default => $listing->status->value,
    };

    $jsonLd = [
        \App\Support\StructuredData::product($listing, $images),
        \App\Support\StructuredData::breadcrumbs(collect($crumbs)->map(
            fn ($crumb, $i) => $i === count($crumbs) - 1 ? ['label' => $crumb['label'], 'url' => $listing->url()] : $crumb,
        )->all()),
    ];
@endphp
<x-app-layout :title="$listing->title"
              :description="\Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $listing->description), 155)"
              :canonical="$listing->url()"
              :robots="$publiclyVisible ? 'index,follow' : 'noindex,nofollow'"
              :image="isset($images[0]) ? url($images[0]['large']) : null"
              og-type="product"
              :json-ld="$publiclyVisible ? $jsonLd : []">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <x-breadcrumbs :items="$crumbs" />

        {{-- Status banner: only the owner and staff can see a listing that is not public --}}
        @unless ($publiclyVisible)
            <x-alert type="warning" class="mt-4">
                <p class="font-bold">{{ __('app.listing_page.banner.'.$bannerKey) }}</p>
                @if ($listing->status === \App\Enums\ListingStatus::Rejected && $listing->rejection_reason)
                    <p class="mt-1">{{ __('app.listing_page.banner.rejected_reason', ['reason' => $listing->rejection_reason]) }}</p>
                @endif
            </x-alert>
        @endunless

        <div class="mt-4 grid gap-6 lg:grid-cols-3">
            <div class="min-w-0 space-y-6 lg:col-span-2">
                {{-- Gallery: first image is server-rendered (fast LCP), the rest load lazily --}}
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white" x-data="{ index: 0, total: {{ count($images) }} }" aria-label="{{ $listing->title }}">
                    <div class="relative aspect-[4/3] bg-slate-100">
                        @forelse ($images as $i => $image)
                            <img x-show="index === {{ $i }}" @if ($i > 0) x-cloak @endif
                                 src="{{ $image['large'] }}"
                                 srcset="{{ $image['thumb'] }} 400w, {{ $image['medium'] }} 800w, {{ $image['large'] }} 1600w"
                                 sizes="(min-width: 1024px) 660px, 100vw"
                                 alt="{{ $listing->title }} — {{ $i + 1 }}"
                                 width="1600" height="1200"
                                 @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif
                                 decoding="async"
                                 class="absolute inset-0 h-full w-full object-contain">
                        @empty
                            <div class="flex h-full w-full flex-col items-center justify-center gap-2 text-slate-400">
                                <svg class="h-14 w-14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/></svg>
                                <span class="text-sm">{{ __('app.listing_page.no_images') }}</span>
                            </div>
                        @endforelse

                        @if (count($images) > 1)
                            <button type="button" @click="index = (index - 1 + total) % total" aria-label="{{ __('app.listing_page.prev_image') }}"
                                    class="absolute start-2 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-800 shadow hover:bg-white">
                                <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button type="button" @click="index = (index + 1) % total" aria-label="{{ __('app.listing_page.next_image') }}"
                                    class="absolute end-2 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-800 shadow hover:bg-white">
                                <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        @endif

                        @if ($listing->isFeatured())
                            <span class="absolute start-3 top-3 rounded-full bg-amber-400 px-3 py-1 text-xs font-bold text-amber-950">{{ __('app.browse.featured') }}</span>
                        @endif

                        @if ($publiclyVisible)
                            <x-favorite-button :listing="$listing" class="absolute end-3 top-3 z-10 !h-11 !w-11" />
                        @endif
                    </div>

                    @if (count($images) > 1)
                        <ul class="flex gap-2 overflow-x-auto p-3">
                            @foreach ($images as $i => $image)
                                <li class="shrink-0">
                                    <button type="button" @click="index = {{ $i }}"
                                            class="block overflow-hidden rounded-lg border-2"
                                            :class="index === {{ $i }} ? 'border-brand-700' : 'border-transparent'"
                                            aria-label="{{ __('app.listing_form.steps.images') }} {{ $i + 1 }}">
                                        <img src="{{ $image['thumb'] }}" alt="" width="400" height="300" loading="lazy" decoding="async" class="h-16 w-20 object-cover">
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- Title, price, meta --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h1 class="text-2xl font-bold leading-snug text-slate-900">{{ $listing->title }}</h1>
                    <p class="mt-3 text-2xl font-bold text-brand-700">{{ $listing->formattedPrice(withType: true) }}</p>

                    <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-600">
                        <li class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ $location }}
                        </li>
                        @if ($listing->published_at)
                            <li class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <time datetime="{{ $listing->published_at->toIso8601String() }}">{{ __('app.listing_page.published', ['date' => $listing->published_at->translatedFormat('j F Y')]) }}</time>
                            </li>
                        @endif
                        <li>{{ __('app.listing_page.views', ['count' => number_format($listing->views)]) }}</li>
                        <li>{{ __('app.listing_page.ad_id', ['id' => $listing->id]) }}</li>
                    </ul>
                </section>

                {{-- Dynamic fields, with units --}}
                @if ($fieldRows->isNotEmpty())
                    <section class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h2 class="text-lg font-bold text-slate-900">{{ __('app.listing_page.details') }}</h2>
                        <dl class="mt-3 grid gap-x-8 sm:grid-cols-2">
                            @foreach ($fieldRows as $row)
                                <div class="flex items-center justify-between gap-4 border-b border-slate-100 py-2.5">
                                    <dt class="text-sm text-slate-500">{{ $row['name'] }}</dt>
                                    <dd class="text-sm font-medium text-slate-900">{{ $row['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-lg font-bold text-slate-900">{{ __('app.listing_page.description') }}</h2>
                    <x-multiline :text="$listing->description" class="mt-3 break-words leading-8 text-slate-800" />
                </section>
            </div>

            {{-- Sidebar --}}
            <aside class="space-y-4">
                {{-- Contact: the number is fetched on demand, it is NOT in the HTML --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5"
                         x-data="contactReveal({ url: @js(route('listings.contact', $listing)) })">
                    <p class="text-2xl font-bold text-brand-700">{{ $listing->formattedPrice() }}</p>

                    <div class="mt-4 space-y-2">
                        <button type="button" x-show="!phone" @click="reveal()" :disabled="loading"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-3 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span x-text="loading ? @js(__('app.listing_page.loading')) : @js(__('app.listing_page.show_phone'))">{{ __('app.listing_page.show_phone') }}</span>
                        </button>

                        <a x-show="phone" x-cloak :href="'tel:' + phone"
                           class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-3 text-sm font-bold text-white hover:bg-brand-800">
                            <span>{{ __('app.listing_page.call') }}</span>
                            <bdi dir="ltr" x-text="phone"></bdi>
                        </a>

                        <a x-show="phone" x-cloak :href="whatsappUrl" @click="trackWhatsapp()" target="_blank" rel="noopener noreferrer"
                           class="flex w-full items-center justify-center gap-2 rounded-lg bg-green-700 px-4 py-3 text-sm font-bold text-white hover:bg-green-800">
                            {{ __('app.listing_page.whatsapp') }}
                        </a>

                        <p x-show="failed" x-cloak role="alert" class="text-sm text-red-700">{{ __('app.listing_page.phone_error') }}</p>

                        @if ($publiclyVisible && auth()->id() !== $listing->user_id)
                            @auth
                                <form method="POST" action="{{ route('listings.message', $listing) }}">
                                    @csrf
                                    <button type="submit"
                                            class="flex w-full items-center justify-center gap-2 rounded-lg border border-brand-700 px-4 py-3 text-sm font-bold text-brand-700 hover:bg-brand-50">
                                        {{ __('app.messages.button') }}
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="flex w-full items-center justify-center gap-2 rounded-lg border border-brand-700 px-4 py-3 text-sm font-bold text-brand-700 hover:bg-brand-50">
                                    {{ __('app.messages.button') }}
                                </a>
                            @endauth
                        @endif
                    </div>
                </section>

                {{-- Seller --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-sm font-bold text-slate-500">{{ __('app.listing_page.seller') }}</h2>
                    <div class="mt-3 flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-100 text-lg font-bold text-brand-800">{{ mb_substr($seller->name, 0, 1) }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-bold text-slate-900">{{ $seller->name }}</p>
                            <p class="text-xs text-slate-500">{{ __('app.listing_page.member_since', ['date' => $seller->created_at->translatedFormat('F Y')]) }}</p>
                            <div class="mt-1">
                                <x-star-rating :rating="$seller->averageRating()" :count="$seller->reviewsCount()" />
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('sellers.show', $seller) }}" class="mt-4 block text-sm font-medium text-brand-700 hover:underline">
                        {{ __('app.listing_page.view_seller') }} ({{ __('app.listing_page.seller_listings', ['count' => $sellerListingsCount]) }})
                    </a>
                </section>

                {{-- Report: signed-in users only, never on your own listing --}}
                @if ($publiclyVisible && auth()->id() !== $listing->user_id)
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 text-center"
                             x-data="{ open: {{ $errors->hasBag('report') ? 'true' : 'false' }} }" @keydown.escape.window="open = false">
                        @auth
                            <button type="button" @click="open = true" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-red-700 hover:underline">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                                {{ __('app.report.button') }}
                            </button>

                            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="report-title">
                                <div class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>

                                <form method="POST" action="{{ route('listings.report', $listing) }}" class="relative w-full max-w-md space-y-4 rounded-2xl bg-white p-6 text-start shadow-xl">
                                    @csrf
                                    <h2 id="report-title" class="text-lg font-bold text-slate-900">{{ __('app.report.title') }}</h2>

                                    <fieldset>
                                        <legend class="mb-2 text-sm font-medium text-slate-700">{{ __('app.report.reason') }}</legend>
                                        <div class="space-y-2">
                                            @foreach (\App\Enums\ReportReason::cases() as $reason)
                                                <label class="flex items-center gap-2 text-sm text-slate-800">
                                                    <input type="radio" name="reason" value="{{ $reason->value }}" @checked(old('reason') === $reason->value) required
                                                           class="border-slate-300 text-brand-700 focus:ring-brand-600">
                                                    {{ $reason->label() }}
                                                </label>
                                            @endforeach
                                        </div>
                                        @if ($errors->report->has('reason'))
                                            <p class="mt-1 text-sm text-red-700" role="alert">{{ $errors->report->first('reason') }}</p>
                                        @endif
                                    </fieldset>

                                    <div>
                                        <label for="report-note" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.report.note') }}</label>
                                        <textarea id="report-note" name="note" rows="3" maxlength="1000"
                                                  class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">{{ old('note') }}</textarea>
                                        <p class="mt-1 text-xs text-slate-500">{{ __('app.report.note_hint') }}</p>
                                        @if ($errors->report->has('note'))
                                            <p class="mt-1 text-sm text-red-700" role="alert">{{ $errors->report->first('note') }}</p>
                                        @endif
                                    </div>

                                    <div class="flex items-center justify-end gap-2">
                                        <x-button type="button" variant="secondary" @click="open = false">{{ __('app.cancel') }}</x-button>
                                        <x-button type="submit" variant="danger">{{ __('app.report.submit') }}</x-button>
                                    </div>
                                </form>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 hover:text-brand-700 hover:underline">{{ __('app.report.login') }}</a>
                        @endauth
                    </section>
                @endif

                {{-- Safety tips --}}
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <h2 class="text-sm font-bold text-amber-900">{{ __('app.listing_page.safety_title') }}</h2>
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-sm leading-6 text-amber-900">
                        @foreach (__('app.listing_page.safety') as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                </section>
            </aside>
        </div>

        @if ($similar->isNotEmpty())
            <section class="mt-10" aria-labelledby="similar-heading">
                <h2 id="similar-heading" class="text-xl font-bold text-slate-900">{{ __('app.listing_page.similar') }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($similar as $item)
                        <x-listing-card :listing="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
