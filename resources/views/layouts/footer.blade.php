@php($footerPages = \App\Models\Page::footerLinks())
<footer class="mt-12 border-t border-slate-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between">
            <div class="max-w-sm">
                <p class="text-lg font-bold text-brand-700">{{ __('app.brand') }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('app.tagline') }}</p>
            </div>

            @if ($footerPages !== [])
                <nav aria-label="{{ __('app.footer.links') }}">
                    <h2 class="text-sm font-bold text-slate-900">{{ __('app.footer.links') }}</h2>
                    <ul class="mt-3 grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
                        @foreach ($footerPages as $page)
                            <li><a href="{{ route('pages.show', $page['slug']) }}" class="text-slate-600 hover:text-brand-700 hover:underline">{{ $page['title'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>

        <p class="mt-8 border-t border-slate-100 pt-4 text-sm text-slate-500">
            {{ __('app.footer.rights', ['year' => now()->year, 'brand' => __('app.brand')]) }}
        </p>
    </div>
</footer>
