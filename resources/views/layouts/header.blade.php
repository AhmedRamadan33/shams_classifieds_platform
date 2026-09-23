<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur"
        x-data="{ drawer: false, menu: false }"
        @keydown.escape.window="drawer = false; menu = false">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
        <button type="button" class="rounded-lg p-2 text-slate-700 hover:bg-slate-100 lg:hidden"
                @click="drawer = true" :aria-expanded="drawer.toString()" aria-controls="mobile-drawer"
                aria-label="{{ __('app.open_menu') }}">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2 text-xl font-bold text-brand-700">
            <svg class="h-8 w-8 text-brand-500" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
                <circle cx="16" cy="16" r="6.5"/>
                <g stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                    <path d="M16 3v4M16 25v4M3 16h4M25 16h4M6.8 6.8l2.8 2.8M22.4 22.4l2.8 2.8M6.8 25.2l2.8-2.8M22.4 9.6l2.8-2.8"/>
                </g>
            </svg>
            <span>{{ __('app.brand') }}</span>
        </a>

        <nav class="hidden shrink-0 items-center gap-1 lg:flex" aria-label="{{ __('app.menu') }}">
            <a href="{{ route('search') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 hover:text-brand-700">{{ __('app.nav.browse') }}</a>
            <a href="{{ route('ad-banners.create') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 hover:text-brand-700">{{ __('app.nav.advertise') }}</a>
        </nav>

        <form action="{{ route('search') }}" method="GET" role="search" class="hidden min-w-0 flex-1 md:block">
            <label for="header-search" class="sr-only">{{ __('app.browse.search_button') }}</label>
            <div class="relative">
                <input id="header-search" type="search" name="q" value="{{ request()->routeIs('search') ? request()->query('q') : '' }}"
                       placeholder="{{ __('app.browse.search_placeholder') }}" autocomplete="off"
                       class="block w-full rounded-lg border border-slate-300 bg-slate-50 py-2 pe-4 ps-10 text-sm focus:border-brand-600 focus:bg-white focus:ring-brand-600">
                <button type="submit" class="absolute inset-y-0 start-0 flex w-10 items-center justify-center text-slate-500 hover:text-brand-700" aria-label="{{ __('app.browse.search_button') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                </button>
            </div>
        </form>

        @stack('header-center')

        <div class="ms-auto flex items-center gap-2">
            @stack('header-actions')

            <a href="{{ route('listings.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-2 text-sm font-bold text-white hover:bg-brand-800 sm:px-4">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                {{ __('app.nav.add_listing') }}
            </a>

            @auth
                @php($unreadCount = Auth::user()->unreadNotifications()->count())
                @php($unreadMessages = Auth::user()->unreadMessagesCount())

                <a href="{{ route('favorites.index') }}" class="hidden rounded-lg p-2 text-slate-600 hover:bg-slate-100 hover:text-red-600 sm:inline-flex" aria-label="{{ __('app.nav.favorites') }}" title="{{ __('app.nav.favorites') }}">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </a>

                <a href="{{ route('messages.index') }}" class="relative hidden rounded-lg p-2 text-slate-600 hover:bg-slate-100 hover:text-brand-700 sm:inline-flex"
                   aria-label="{{ __('app.nav.messages') }}@if ($unreadMessages) ({{ $unreadMessages }})@endif" title="{{ __('app.nav.messages') }}">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8-1.17 0-2.29-.196-3.31-.554L3 21l1.554-4.69C3.564 15.29 3 13.696 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    @if ($unreadMessages)
                        <span class="absolute end-0 top-0 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-bold text-white" data-testid="unread-messages-badge">{{ $unreadMessages > 9 ? '9+' : $unreadMessages }}</span>
                    @endif
                </a>

                <a href="{{ route('notifications.index') }}" class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100 hover:text-brand-700"
                   aria-label="{{ __('app.nav.notifications') }}@if ($unreadCount) ({{ $unreadCount }})@endif" title="{{ __('app.nav.notifications') }}">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                    @if ($unreadCount)
                        <span class="absolute end-0 top-0 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-bold text-white" data-testid="unread-badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                    @endif
                </a>

                <div class="relative hidden lg:block">
                    <button type="button" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
                            @click="menu = !menu" :aria-expanded="menu.toString()" aria-haspopup="true">
                        @if (Auth::user()->avatarUrl())
                            <img src="{{ Auth::user()->avatarUrl() }}" alt="" width="32" height="32" class="h-8 w-8 rounded-full object-cover">
                        @else
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-800">{{ mb_substr(Auth::user()->name, 0, 1) }}</span>
                        @endif
                        <span class="max-w-[8rem] truncate">{{ Auth::user()->name }}</span>
                    </button>
                    <div x-show="menu" x-cloak @click.outside="menu = false"
                         class="absolute end-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                        @if (Auth::user()->isStaff())
                            <a href="{{ url('/admin') }}" class="block px-4 py-2 text-sm font-medium text-brand-800 hover:bg-brand-50">{{ __('app.nav.admin') }}</a>
                        @endif
                        <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('app.nav.dashboard') }}</a>
                        <a href="{{ route('favorites.index') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('app.nav.favorites') }}</a>
                        <a href="{{ route('messages.index') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('app.nav.messages') }}@if ($unreadMessages) ({{ $unreadMessages }})@endif</a>
                        <a href="{{ route('saved-searches.index') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('app.nav.saved_searches') }}</a>
                        <a href="{{ route('store.edit') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('app.nav.store') }}</a>
                        <a href="{{ route('subscribe') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('app.nav.subscribe') }}</a>
                        <a href="{{ route('ad-banners.index') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('app.nav.my_banners') }}</a>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('app.nav.profile') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-start text-sm text-slate-700 hover:bg-slate-50">{{ __('app.nav.logout') }}</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 lg:inline-block">{{ __('app.nav.login') }}</a>
                <a href="{{ route('register') }}" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 lg:inline-block">{{ __('app.nav.register') }}</a>
            @endauth
        </div>
    </div>

    <div id="mobile-drawer" x-show="drawer" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('app.menu') }}">
        <div class="absolute inset-0 bg-slate-900/50" @click="drawer = false" x-transition.opacity></div>
        <nav class="absolute inset-y-0 start-0 w-72 max-w-[85%] overflow-y-auto bg-white p-4 shadow-xl"
             x-show="drawer"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="ltr:-translate-x-full rtl:translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="ltr:-translate-x-full rtl:translate-x-full">
            <div class="mb-4 flex items-center justify-between">
                <span class="text-lg font-bold text-brand-700">{{ __('app.brand') }}</span>
                <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100" @click="drawer = false" aria-label="{{ __('app.close') }}">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('search') }}" method="GET" role="search" class="mb-4">
                <label for="drawer-search" class="sr-only">{{ __('app.browse.search_button') }}</label>
                <input id="drawer-search" type="search" name="q" placeholder="{{ __('app.browse.search_placeholder') }}"
                       class="block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-brand-600 focus:bg-white focus:ring-brand-600">
            </form>

            <ul class="space-y-1 text-base">
                <li><a href="{{ route('home') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.home') }}</a></li>
                <li><a href="{{ route('listings.create') }}" class="block rounded-lg bg-brand-50 px-3 py-2.5 font-bold text-brand-800 hover:bg-brand-100">{{ __('app.nav.add_listing') }}</a></li>
                <li><a href="{{ route('search') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.browse') }}</a></li>
                <li><a href="{{ route('ad-banners.create') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.advertise') }}</a></li>
                @stack('drawer-links')
                @auth
                    @if (Auth::user()->isStaff())
                        <li><a href="{{ url('/admin') }}" class="block rounded-lg px-3 py-2.5 font-medium text-brand-800 hover:bg-brand-50">{{ __('app.nav.admin') }}</a></li>
                    @endif
                    <li><a href="{{ route('dashboard') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.dashboard') }}</a></li>
                    <li><a href="{{ route('favorites.index') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.favorites') }}</a></li>
                    <li><a href="{{ route('messages.index') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.messages') }}@if ($unreadMessages) ({{ $unreadMessages }})@endif</a></li>
                    <li><a href="{{ route('saved-searches.index') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.saved_searches') }}</a></li>
                    <li><a href="{{ route('store.edit') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.store') }}</a></li>
                    <li><a href="{{ route('subscribe') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.subscribe') }}</a></li>
                    <li><a href="{{ route('ad-banners.index') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.my_banners') }}</a></li>
                    <li><a href="{{ route('notifications.index') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.notifications') }}</a></li>
                    <li><a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.profile') }}</a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full rounded-lg px-3 py-2.5 text-start font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.logout') }}</button>
                        </form>
                    </li>
                @else
                    <li><a href="{{ route('login') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.login') }}</a></li>
                    <li><a href="{{ route('register') }}" class="block rounded-lg px-3 py-2.5 font-medium text-slate-800 hover:bg-slate-100">{{ __('app.nav.register') }}</a></li>
                @endauth
            </ul>
        </nav>
    </div>
</header>
