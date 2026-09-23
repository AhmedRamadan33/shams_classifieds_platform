<x-app-layout :title="__('app.seller.title', ['name' => $seller->name])">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <x-breadcrumbs :items="[
            ['label' => __('app.nav.home'), 'url' => route('home')],
            ['label' => $seller->name, 'url' => null],
        ]" />

        <header class="mt-4 flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 text-xl font-bold text-brand-800">{{ mb_substr($seller->name, 0, 1) }}</span>
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ $seller->name }}</h1>
                <p class="mt-1 text-sm text-slate-600">
                    {{ __('app.seller.member_since', ['date' => $seller->created_at->translatedFormat('F Y')]) }}
                    · {{ __('app.seller.count', ['count' => number_format($listings->total())]) }}
                </p>
                <div class="mt-2">
                    <x-star-rating :rating="$seller->averageRating()" :count="$seller->reviewsCount()" />
                </div>
            </div>
        </header>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div>
                @if ($listings->isEmpty())
                    <x-empty-state :title="__('app.seller.empty')" />
                @else
                    <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3">
                        @foreach ($listings as $listing)
                            <x-listing-card :listing="$listing" />
                        @endforeach
                    </div>

                    <div class="mt-8">
                        <x-pagination :paginator="$listings" />
                    </div>
                @endif
            </div>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-base font-bold text-slate-900">{{ __('app.reviews.title') }}</h2>

                    @auth
                        @if (auth()->id() !== $seller->id)
                            <form method="POST" action="{{ route('reviews.store', $seller) }}" class="mt-3 space-y-3"
                                  x-data="{ rating: {{ $myReview?->rating ?? 0 }} }">
                                @csrf
                                <input type="hidden" name="rating" x-model="rating">

                                <div>
                                    <span class="mb-1 block text-xs font-medium text-slate-700">{{ __('app.reviews.rating') }}</span>
                                    <div class="flex gap-1">
                                        <template x-for="star in [1,2,3,4,5]" :key="star">
                                            <button type="button" @click="rating = star" class="p-0.5" :aria-label="star + ' / 5'">
                                                <svg class="h-6 w-6" :class="star <= rating ? 'fill-amber-400 text-amber-400' : 'fill-slate-200 text-slate-200'" viewBox="0 0 20 20" stroke="none">
                                                    <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1 1 5.8L10 14.9l-5.21 2.62 1-5.8-4.21-4.1 5.82-.85L10 1.5z"/>
                                                </svg>
                                            </button>
                                        </template>
                                    </div>
                                    @error('rating')
                                        <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                <x-textarea name="comment" :label="__('app.reviews.comment')" :value="$myReview?->comment" rows="3" />

                                <x-button type="submit" size="sm">{{ $myReview ? __('app.reviews.update') : __('app.reviews.submit') }}</x-button>
                            </form>

                            @if ($myReview)
                                <form method="POST" action="{{ route('reviews.destroy', $myReview) }}" class="mt-2"
                                      x-data @submit="if (! confirm(@js(__('app.reviews.confirm_delete')))) $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-700 hover:underline">{{ __('app.reviews.delete') }}</button>
                                </form>
                            @endif
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="mt-3 block text-sm font-medium text-brand-700 hover:underline">{{ __('app.reviews.login_to_review') }}</a>
                    @endauth
                </section>

                @if ($reviews->isEmpty())
                    <p class="text-sm text-slate-500">{{ __('app.reviews.no_reviews') }}</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($reviews as $review)
                            <li class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-slate-900">{{ $review->reviewer->name }}</span>
                                    <x-star-rating :rating="(float) $review->rating" :show-count="false" />
                                </div>
                                @if ($review->comment)
                                    <p class="mt-2 text-sm text-slate-700">{{ $review->comment }}</p>
                                @endif
                                <p class="mt-1 text-xs text-slate-500">{{ $review->created_at->translatedFormat('j F Y') }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </aside>
        </div>
    </div>
</x-app-layout>
