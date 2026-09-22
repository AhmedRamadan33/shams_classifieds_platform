@props(['listing'])
@php($favorited = app(\App\Support\FavoriteIds::class)->has($listing->id))
<button type="button"
        x-data="favoriteButton({ url: @js(route('listings.favorite', $listing)), loginUrl: @js(route('login')), favorited: @js($favorited) })"
        @click.prevent.stop="toggle()"
        :aria-pressed="favorited.toString()"
        aria-label="{{ __('app.favorites.toggle') }}"
        {{ $attributes->merge(['class' => 'flex h-9 w-9 items-center justify-center rounded-full bg-white/95 shadow transition hover:scale-110']) }}>
    <svg class="h-5 w-5 transition" :class="favorited ? 'fill-red-500 text-red-500' : 'fill-none text-slate-500'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
    </svg>
</button>
