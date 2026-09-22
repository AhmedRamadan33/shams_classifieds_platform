<x-app-layout :title="__('app.my_listings.title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ __('app.my_listings.title') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('app.dashboard.welcome', ['name' => Auth::user()->name]) }}</p>
            </div>
            <x-button :href="route('listings.create')">{{ __('app.my_listings.add') }}</x-button>
        </div>

        {{-- Tabs by status --}}
        <nav class="mt-6 overflow-x-auto border-b border-slate-200" aria-label="{{ __('app.my_listings.title') }}">
            <ul class="flex min-w-max gap-1">
                @foreach ($tabs as $item)
                    @php $active = $item === $tab; @endphp
                    <li>
                        <a href="{{ route('dashboard', ['status' => $item]) }}"
                           @if ($active) aria-current="page" @endif
                           class="-mb-px flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-bold {{ $active ? 'border-brand-700 text-brand-800' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            {{ \App\Enums\ListingStatus::from($item)->label() }}
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $active ? 'bg-brand-100 text-brand-800' : 'bg-slate-100 text-slate-600' }}">{{ $counts[$item] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="mt-6 space-y-4">
            @forelse ($listings as $listing)
                @php
                    $status = $listing->status;
                    $isExpired = $listing->isExpired();
                    $badge = $isExpired ? \App\Enums\ListingStatus::Expired : $status;
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex gap-4">
                        <a href="{{ $listing->url() }}" class="block h-24 w-32 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                            @if ($cover = $listing->coverUrl('thumb'))
                                <img src="{{ $cover }}" alt="{{ $listing->title }}" width="400" height="300" loading="lazy" class="h-full w-full object-cover">
                            @endif
                        </a>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h2 class="min-w-0 text-base font-bold text-slate-900">
                                    <a href="{{ $listing->url() }}" class="hover:text-brand-700">{{ $listing->title }}</a>
                                </h2>
                                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $badge->badgeClasses() }}">{{ $badge->label() }}</span>
                            </div>

                            <p class="mt-1 text-sm font-bold text-brand-700">{{ $listing->formattedPrice() }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $listing->category->name }} · {{ $listing->governorate->name }}
                                @if ($listing->published_at) · {{ __('app.my_listings.published', ['date' => $listing->published_at->translatedFormat('j F Y')]) }} @else · {{ __('app.my_listings.created', ['date' => $listing->created_at->translatedFormat('j F Y')]) }} @endif
                                @if ($listing->expires_at)
                                    · {{ $isExpired ? __('app.my_listings.expired_on', ['date' => $listing->expires_at->translatedFormat('j F Y')]) : __('app.my_listings.expires', ['date' => $listing->expires_at->translatedFormat('j F Y')]) }}
                                @endif
                            </p>

                            @if ($status === \App\Enums\ListingStatus::Rejected && $listing->rejection_reason)
                                <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-900">{{ __('app.my_listings.rejection_reason', ['reason' => $listing->rejection_reason]) }}</p>
                            @endif

                            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                                <li>{{ __('app.my_listings.views', ['count' => number_format($listing->views)]) }}</li>
                                <li>{{ __('app.my_listings.phone_clicks', ['count' => number_format($listing->phone_clicks_count)]) }}</li>
                                <li>{{ __('app.my_listings.whatsapp_clicks', ['count' => number_format($listing->whatsapp_clicks_count)]) }}</li>
                                <li>{{ __('app.my_listings.favorites', ['count' => number_format($listing->favorites_count)]) }}</li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                        <x-button :href="route('listings.edit', $listing)" variant="secondary" size="sm">{{ __('app.my_listings.edit') }}</x-button>

                        @if ($renewable->has($listing->id))
                            <form method="POST" action="{{ route('listings.renew', $listing) }}">
                                @csrf
                                <x-button type="submit" size="sm">{{ __('app.my_listings.renew') }}</x-button>
                            </form>
                        @endif

                        @if ($status === \App\Enums\ListingStatus::Active && ! $isExpired)
                            <form method="POST" action="{{ route('listings.sold', $listing) }}">
                                @csrf
                                <x-button type="submit" variant="secondary" size="sm">{{ __('app.my_listings.sold') }}</x-button>
                            </form>

                            <x-button :href="route('listings.feature', $listing)" variant="secondary" size="sm" class="text-amber-700 hover:bg-amber-50">
                                {{ $listing->isFeatured() ? __('app.my_listings.featured_until', ['date' => $listing->featured_until->translatedFormat('j F Y')]) : __('app.my_listings.feature') }}
                            </x-button>
                        @endif

                        <form method="POST" action="{{ route('listings.destroy', $listing) }}" class="ms-auto"
                              x-data @submit="if (! confirm(@js(__('app.my_listings.confirm_delete')))) $event.preventDefault()">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="ghost" size="sm" class="text-red-700 hover:bg-red-50">{{ __('app.my_listings.delete') }}</x-button>
                        </form>
                    </div>
                </article>
            @empty
                <x-empty-state :title="__('app.my_listings.empty_title')" :message="__('app.my_listings.empty_message')">
                    <x-button :href="route('listings.create')">{{ __('app.my_listings.add') }}</x-button>
                </x-empty-state>
            @endforelse
        </div>

        <div class="mt-8">
            <x-pagination :paginator="$listings" />
        </div>
    </div>
</x-app-layout>
