<x-app-layout :title="$listing ? __('app.listing_form.edit_title') : __('app.listing_form.create_title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6" x-data="listingForm(@js($payload))">
        <h1 class="text-2xl font-bold text-slate-900">
            {{ $listing ? __('app.listing_form.edit_title') : __('app.listing_form.create_title') }}
        </h1>

        {{-- Progress --}}
        <ol class="mt-6 grid grid-cols-4 gap-2" aria-label="{{ __('app.listing_form.progress') }}">
            <template x-for="n in totalSteps" :key="n">
                <li>
                    <button type="button" @click="goTo(n)" :disabled="n >= step"
                            class="flex w-full flex-col items-center gap-1 text-center text-xs sm:text-sm"
                            :class="n <= step ? 'text-brand-800' : 'text-slate-500'"
                            :aria-current="n === step ? 'step' : null">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 text-sm font-bold"
                              :class="n < step ? 'border-brand-700 bg-brand-700 text-white' : (n === step ? 'border-brand-700 text-brand-800' : 'border-slate-300 text-slate-500')"
                              x-text="n"></span>
                        <span x-text="[msg.steps.category, msg.steps.details, msg.steps.images, msg.steps.review][n - 1]"></span>
                    </button>
                </li>
            </template>
        </ol>

        <form method="POST"
              action="{{ $listing ? route('listings.update', $listing) : route('listings.store') }}"
              enctype="multipart/form-data" novalidate class="mt-6"
              @submit="onSubmit($event)">
            @csrf
            @if ($listing)
                @method('PUT')
            @endif

            <x-honeypot />
            <input type="hidden" name="category_id" :value="categoryId">
            <input type="hidden" name="cover" :value="coverValue()">
            <template x-for="id in removed" :key="id"><input type="hidden" name="remove_images[]" :value="id"></template>

            <div x-show="error('limit')" x-cloak role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" x-text="error('limit')"></div>

            {{-- STEP 1: category --}}
            <section x-show="step === 1" x-cloak class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-bold text-slate-900" x-text="msg.choose_category"></h2>

                <div x-show="path.length" class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-slate-500" x-text="msg.selected_category"></span>
                    <template x-for="(node, index) in path" :key="node.id">
                        <button type="button" @click="resetPathTo(index)"
                                class="rounded-full bg-brand-50 px-3 py-1 font-medium text-brand-800 hover:bg-brand-100" x-text="node.name"></button>
                    </template>
                </div>

                <p x-show="error('category_id')" role="alert" class="mt-3 text-sm text-red-700" x-text="error('category_id')"></p>

                <div x-show="!categoryId" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <template x-for="node in options()" :key="node.id">
                        <button type="button" @click="choose(node)"
                                class="flex min-h-[5.5rem] flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-3 text-center text-sm font-medium text-slate-800 transition hover:border-brand-500 hover:bg-brand-50">
                            <svg x-show="path.length === 0" class="h-7 w-7 text-brand-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path :d="node.iconPath"/>
                            </svg>
                            <span x-text="node.name"></span>
                        </button>
                    </template>
                </div>

                <p x-show="categoryId" class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-900">
                    <span x-text="msg.selected_category"></span>:
                    <strong x-text="categoryLabel()"></strong>
                </p>
            </section>

            {{-- STEP 2: details --}}
            <section x-show="step === 2" x-cloak class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-bold text-slate-900" x-text="msg.details_title"></h2>
                    <p class="text-sm text-slate-600">
                        <span x-text="categoryLabel()"></span>
                        <button type="button" class="ms-2 font-medium text-brand-700 hover:underline" @click="step = 1" x-text="msg.change"></button>
                    </p>
                </div>

                <div>
                    <label for="title" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.listing_form.title') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="title" type="text" name="title" x-model="title" maxlength="150" :placeholder="msg.title_placeholder"
                           class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                           :class="error('title') ? 'border-red-500' : ''">
                    <p class="mt-1 text-xs text-slate-500" x-show="!error('title')" x-text="msg.title_hint"></p>
                    <p class="mt-1 text-sm text-red-700" role="alert" x-show="error('title')" x-text="error('title')"></p>
                </div>

                <div>
                    <label for="description" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.listing_form.description') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <textarea id="description" name="description" x-model="description" rows="6" maxlength="5000"
                              class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                              :class="error('description') ? 'border-red-500' : ''"></textarea>
                    <p class="mt-1 text-xs text-slate-500" x-show="!error('description')" x-text="msg.description_hint"></p>
                    <p class="mt-1 text-sm text-red-700" role="alert" x-show="error('description')" x-text="error('description')"></p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="price_type" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.listing_form.price_type') }}</label>
                        <select id="price_type" name="price_type" x-model="priceType"
                                class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600">
                            <template x-for="type in priceTypes" :key="type.value">
                                <option :value="type.value" :selected="type.value === priceType" x-text="type.label"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-sm text-red-700" role="alert" x-show="error('price_type')" x-text="error('price_type')"></p>
                    </div>

                    <div x-show="needsPrice()">
                        <label for="price" class="mb-1 block text-sm font-medium text-slate-700">
                            {{ __('app.listing_form.price') }} (<span x-text="currency"></span>) <span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        <input id="price" type="text" name="price" x-model="price" inputmode="decimal" autocomplete="off" :disabled="!needsPrice()"
                               class="ltr-input block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                               :class="error('price') ? 'border-red-500' : ''">
                        <p class="mt-1 text-sm text-red-700" role="alert" x-show="error('price')" x-text="error('price')"></p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="governorate_id" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.listing_form.governorate') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                        <select id="governorate_id" name="governorate_id" x-model="governorateId" @change="cityId = ''"
                                class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                :class="error('governorate_id') ? 'border-red-500' : ''">
                            <option value="">{{ __('app.listing_form.choose') }}</option>
                            <template x-for="governorate in governorates" :key="governorate.id">
                                <option :value="governorate.id" :selected="String(governorate.id) === String(governorateId)" x-text="governorate.name"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-sm text-red-700" role="alert" x-show="error('governorate_id')" x-text="error('governorate_id')"></p>
                    </div>

                    <div>
                        <label for="city_id" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.listing_form.city') }}</label>
                        <select id="city_id" name="city_id" x-model="cityId"
                                class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600">
                            <option value="">{{ __('app.listing_form.choose') }}</option>
                            <template x-for="city in cities()" :key="city.id">
                                <option :value="city.id" :selected="String(city.id) === String(cityId)" x-text="city.name"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-sm text-red-700" role="alert" x-show="error('city_id')" x-text="error('city_id')"></p>
                    </div>
                </div>

                <div>
                    <label for="phone" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.listing_form.phone') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="phone" type="tel" name="phone" x-model="phone" inputmode="tel" autocomplete="tel"
                           class="ltr-input block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                           :class="error('phone') ? 'border-red-500' : ''">
                    <p class="mt-1 text-xs text-slate-500" x-show="!error('phone')" x-text="msg.phone_hint"></p>
                    <p class="mt-1 text-sm text-red-700" role="alert" x-show="error('phone')" x-text="error('phone')"></p>
                </div>

                {{-- Category specific fields, fetched from /api/categories/{id}/fields --}}
                <div x-show="loadingFields" class="text-sm text-slate-500" x-text="msg.loading_fields"></div>
                <div x-show="fieldsError" role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    <span x-text="msg.fields_failed"></span>
                    <button type="button" class="ms-2 font-bold underline" @click="loadFields()" x-text="msg.retry"></button>
                </div>

                <div x-show="fieldDefs.length" class="space-y-4 border-t border-slate-100 pt-5">
                    <h3 class="text-base font-bold text-slate-900" x-text="msg.extra_fields"></h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <template x-for="field in fieldDefs" :key="field.key">
                            <div>
                                <label :for="'field_' + field.key" class="mb-1 block text-sm font-medium text-slate-700">
                                    <span x-text="field.name"></span><span x-show="field.unit" x-text="' (' + field.unit + ')'"></span>
                                    <span x-show="field.is_required" class="text-red-600" aria-hidden="true">*</span>
                                </label>

                                <template x-if="field.type === 'text'">
                                    <input type="text" :id="'field_' + field.key" :name="'fields[' + field.key + ']'" x-model="fields[field.key]" maxlength="255"
                                           class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                           :class="error('fields.' + field.key) ? 'border-red-500' : ''">
                                </template>

                                <template x-if="field.type === 'number'">
                                    <input type="text" inputmode="decimal" autocomplete="off" :id="'field_' + field.key" :name="'fields[' + field.key + ']'" x-model="fields[field.key]"
                                           class="ltr-input block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                           :class="error('fields.' + field.key) ? 'border-red-500' : ''">
                                </template>

                                <template x-if="field.type === 'select'">
                                    <select :id="'field_' + field.key" :name="'fields[' + field.key + ']'" x-model="fields[field.key]"
                                            class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                            :class="error('fields.' + field.key) ? 'border-red-500' : ''">
                                        <option value="">{{ __('app.listing_form.choose') }}</option>
                                        <template x-for="option in field.options" :key="option">
                                            <option :value="option" :selected="fields[field.key] === option" x-text="option"></option>
                                        </template>
                                    </select>
                                </template>

                                <template x-if="field.type === 'boolean'">
                                    <div class="flex items-center gap-2 py-2">
                                        <input type="hidden" :name="'fields[' + field.key + ']'" value="0">
                                        <input type="checkbox" :id="'field_' + field.key" :name="'fields[' + field.key + ']'" value="1"
                                               :checked="fields[field.key] === '1'"
                                               @change="fields[field.key] = $event.target.checked ? '1' : '0'"
                                               class="rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                                        <span class="text-sm text-slate-700" x-text="msg.yes"></span>
                                    </div>
                                </template>

                                <p class="mt-1 text-sm text-red-700" role="alert" x-show="error('fields.' + field.key)" x-text="error('fields.' + field.key)"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            {{-- STEP 3: images --}}
            <section x-show="step === 3" x-cloak class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-bold text-slate-900" x-text="msg.images_title"></h2>
                <p class="mt-1 text-sm text-slate-600"
                   x-text="msg.images_hint.replace(':max', limits.maxImages).replace(':size', Math.round(limits.maxImageKb / 1024))"></p>

                <input type="file" name="images[]" x-ref="fileInput" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1" @change="addFiles($event)">

                <button type="button" @click="$refs.fileInput.click()"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg border border-dashed border-brand-500 bg-brand-50 px-4 py-3 text-sm font-bold text-brand-800 hover:bg-brand-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span x-text="msg.add_images"></span>
                </button>

                <p class="mt-3 text-sm text-red-700" role="alert" x-show="imagesError()" x-text="imagesError()"></p>

                <p class="mt-4 text-sm text-slate-500" x-show="imageList().length === 0" x-text="msg.no_images"></p>

                <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3" x-show="imageList().length">
                    <template x-for="image in imageList()" :key="image.key">
                        <li class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                            <div class="relative aspect-[4/3]">
                                <img :src="image.url" alt="" class="h-full w-full object-cover">
                                <span x-show="coverItem() && coverItem().key === image.key"
                                      class="absolute start-2 top-2 rounded-full bg-brand-700 px-2 py-0.5 text-xs font-bold text-white" x-text="msg.cover"></span>
                            </div>
                            <div class="flex items-center justify-between gap-2 p-2 text-xs">
                                <button type="button" class="font-medium text-brand-700 hover:underline"
                                        x-show="!coverItem() || coverItem().key !== image.key" @click="setCover(image)" x-text="msg.make_cover"></button>
                                <span x-show="coverItem() && coverItem().key === image.key" class="text-slate-500" x-text="msg.cover"></span>
                                <button type="button" class="font-medium text-red-700 hover:underline" @click="removeImage(image)" x-text="msg.remove"></button>
                            </div>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- STEP 4: review --}}
            <section x-show="step === 4" x-cloak class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-bold text-slate-900" x-text="msg.review_title"></h2>
                <p class="mt-1 text-sm text-slate-600" x-text="msg.review_hint"></p>

                <dl class="mt-4 divide-y divide-slate-100 text-sm">
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:gap-4"><dt class="w-40 shrink-0 text-slate-500" x-text="msg.steps.category"></dt><dd class="font-medium text-slate-900" x-text="categoryLabel()"></dd></div>
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:gap-4"><dt class="w-40 shrink-0 text-slate-500" x-text="msg.title"></dt><dd class="font-medium text-slate-900" x-text="title"></dd></div>
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:gap-4"><dt class="w-40 shrink-0 text-slate-500" x-text="msg.price"></dt><dd class="font-medium text-slate-900" x-text="priceLabel()"></dd></div>
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:gap-4"><dt class="w-40 shrink-0 text-slate-500" x-text="msg.governorate"></dt><dd class="font-medium text-slate-900" x-text="[governorateName(), cityName()].filter(Boolean).join(' - ')"></dd></div>
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:gap-4"><dt class="w-40 shrink-0 text-slate-500" x-text="msg.phone"></dt><dd dir="ltr" class="text-end font-medium text-slate-900 sm:text-start" x-text="phone"></dd></div>
                    <template x-for="field in fieldDefs" :key="field.key">
                        <div class="flex flex-col gap-1 py-3 sm:flex-row sm:gap-4" x-show="fieldDisplay(field) !== ''">
                            <dt class="w-40 shrink-0 text-slate-500" x-text="field.name"></dt>
                            <dd class="font-medium text-slate-900" x-text="fieldDisplay(field)"></dd>
                        </div>
                    </template>
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:gap-4"><dt class="w-40 shrink-0 text-slate-500" x-text="msg.steps.images"></dt><dd class="font-medium text-slate-900" x-text="imageList().length"></dd></div>
                </dl>

                @if (config('classifieds.require_review'))
                    <x-alert type="info" class="mt-4">{{ __('app.listing_form.moderation_notice') }}</x-alert>
                @endif
            </section>

            {{-- Bot check (only when a provider is configured) on the last step of a new listing --}}
            @unless ($listing)
                <div x-show="step === totalSteps" x-cloak class="mt-4"><x-captcha /></div>
            @endunless

            {{-- Navigation --}}
            <div class="mt-6 flex items-center justify-between gap-3">
                <button type="button" x-show="step > 1" @click="back()"
                        class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50" x-text="msg.back"></button>
                <span x-show="step === 1"></span>

                <button type="button" x-show="step < totalSteps && (step > 1 || categoryId)" @click="next()"
                        class="inline-flex items-center rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white hover:bg-brand-800" x-text="msg.next"></button>

                <button type="submit" x-show="step === totalSteps" :disabled="submitting"
                        class="inline-flex items-center rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60"
                        x-text="submitting ? msg.submitting : (mode === 'edit' ? msg.save : msg.publish)"></button>
            </div>
        </form>
    </div>
</x-app-layout>
