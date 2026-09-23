@props(['action', 'search', 'hidden' => [], 'selectedGovernorate' => null, 'clearUrl'])
@php
    $governorates = \App\Services\Geography::all();
    $fields = $search->filterableFields();
    $current = (array) request()->query('f', []);

    $geo = $governorates->map(fn ($governorate) => [
        'slug' => $governorate->slug,
        'cities' => $governorate->cities->map(fn ($city) => ['id' => (string) $city->id, 'name' => $city->name])->values()->all(),
    ])->values()->all();

    $priceTypes = \App\Enums\PriceType::options();
@endphp
<div x-data="{
        open: false,
        governorate: @js($selectedGovernorate ?? (string) request()->query('governorate', '')),
        city: @js((string) request()->query('city', '')),
        geo: @js($geo),
        get cities() {
            const found = this.geo.find((item) => item.slug === this.governorate);
            return found ? found.cities : [];
        },
    }"
    @keydown.escape.window="open = false">

    <button type="button" @click="open = true"
            class="mb-4 inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 lg:hidden"
            aria-haspopup="dialog">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8.5V19l-4 2v-8.5L3 4z"/></svg>
        {{ __('app.browse.show_filters') }}
    </button>

    <div class="fixed inset-0 z-50 lg:static lg:z-auto lg:block" :class="open ? 'block' : 'hidden'"
         role="dialog" aria-modal="true" aria-label="{{ __('app.browse.filters') }}">
        <div class="absolute inset-0 bg-slate-900/50 lg:hidden" @click="open = false"></div>

        <aside class="absolute inset-y-0 start-0 w-80 max-w-[90%] overflow-y-auto bg-white p-5 shadow-xl lg:static lg:w-auto lg:max-w-none lg:overflow-visible lg:rounded-2xl lg:border lg:border-slate-200 lg:shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">{{ __('app.browse.filters') }}</h2>
                <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden" @click="open = false" aria-label="{{ __('app.close') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="GET" action="{{ $action }}" class="space-y-5">
                @foreach ($hidden as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach

                <div>
                    <label for="filter-governorate" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.browse.governorate') }}</label>
                    <select id="filter-governorate" name="governorate" x-model="governorate" @change="city = ''"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                        <option value="">{{ __('app.browse.all_governorates') }}</option>
                        @foreach ($governorates as $governorate)
                            <option value="{{ $governorate->slug }}" @selected(($selectedGovernorate ?? request()->query('governorate')) === $governorate->slug)>{{ $governorate->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="cities.length" x-cloak>
                    <label for="filter-city" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.browse.city') }}</label>
                    <select id="filter-city" name="city" x-model="city" :disabled="!cities.length"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                        <option value="">{{ __('app.browse.all_cities') }}</option>
                        <template x-for="item in cities" :key="item.id">
                            <option :value="item.id" :selected="item.id === city" x-text="item.name"></option>
                        </template>
                    </select>
                </div>

                <fieldset>
                    <legend class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.browse.price') }}</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" inputmode="decimal" name="price_min" value="{{ request()->query('price_min') }}" placeholder="{{ __('app.browse.price_from') }}" aria-label="{{ __('app.browse.price_from') }}"
                               class="ltr-input block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                        <input type="text" inputmode="decimal" name="price_max" value="{{ request()->query('price_max') }}" placeholder="{{ __('app.browse.price_to') }}" aria-label="{{ __('app.browse.price_to') }}"
                               class="ltr-input block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                    </div>
                </fieldset>

                <div>
                    <label for="filter-price-type" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.browse.price_type') }}</label>
                    <select id="filter-price-type" name="price_type" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                        <option value="">{{ __('app.browse.any') }}</option>
                        @foreach ($priceTypes as $value => $label)
                            <option value="{{ $value }}" @selected(request()->query('price_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @foreach ($fields as $field)
                    @php $value = $current[$field->key] ?? null; @endphp
                    <div>
                        @if ($field->type === \App\Enums\FieldType::Number)
                            <span class="mb-1 block text-sm font-medium text-slate-700">{{ $field->name }}@if ($field->unit) ({{ $field->unit }})@endif</span>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" inputmode="decimal" name="f[{{ $field->key }}][min]" value="{{ is_array($value) ? ($value['min'] ?? '') : '' }}"
                                       placeholder="{{ __('app.browse.min') }}" aria-label="{{ $field->name }} — {{ __('app.browse.min') }}"
                                       class="ltr-input block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                                <input type="text" inputmode="decimal" name="f[{{ $field->key }}][max]" value="{{ is_array($value) ? ($value['max'] ?? '') : '' }}"
                                       placeholder="{{ __('app.browse.max') }}" aria-label="{{ $field->name }} — {{ __('app.browse.max') }}"
                                       class="ltr-input block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                            </div>
                        @elseif ($field->type === \App\Enums\FieldType::Select)
                            <label for="filter-{{ $field->key }}" class="mb-1 block text-sm font-medium text-slate-700">{{ $field->name }}</label>
                            <select id="filter-{{ $field->key }}" name="f[{{ $field->key }}]" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                                <option value="">{{ __('app.browse.any') }}</option>
                                @foreach ($field->optionValues() as $option)
                                    <option value="{{ $option }}" @selected(is_string($value) && $value === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @elseif ($field->type === \App\Enums\FieldType::Boolean)
                            <label for="filter-{{ $field->key }}" class="mb-1 block text-sm font-medium text-slate-700">{{ $field->name }}</label>
                            <select id="filter-{{ $field->key }}" name="f[{{ $field->key }}]" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                                <option value="">{{ __('app.browse.any') }}</option>
                                <option value="1" @selected($value === '1')>{{ __('app.yes') }}</option>
                                <option value="0" @selected($value === '0')>{{ __('app.no') }}</option>
                            </select>
                        @else
                            <label for="filter-{{ $field->key }}" class="mb-1 block text-sm font-medium text-slate-700">{{ $field->name }}</label>
                            <input id="filter-{{ $field->key }}" type="text" name="f[{{ $field->key }}]" value="{{ is_string($value) ? $value : '' }}"
                                   class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                        @endif
                    </div>
                @endforeach

                <div>
                    <label for="filter-sort" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.browse.sort_by') }}</label>
                    <select id="filter-sort" name="sort" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-600 focus:ring-brand-600">
                        @foreach (\App\Queries\ListingSearch::SORTS as $sort)
                            <option value="{{ $sort }}" @selected($search->sort() === $sort)>{{ __('app.browse.sorts.'.$sort) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button type="submit" class="flex-1 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-800">{{ __('app.browse.apply_filters') }}</button>
                    <a href="{{ $clearUrl }}" class="text-sm font-medium text-slate-600 hover:text-brand-700 hover:underline">{{ __('app.browse.clear_filters') }}</a>
                </div>
            </form>
        </aside>
    </div>
</div>
