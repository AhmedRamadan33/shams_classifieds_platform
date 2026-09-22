<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-seo :title="$title" :description="$description" :canonical="$canonical" :robots="$robots" :image="$image" :og-type="$ogType" :json-ld="$jsonLd" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-800 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">{{ __('app.skip_to_content') }}</a>

    @include('layouts.header')

    <main id="main" class="flex-1">
        @if (session('status') || session('success') || session('error'))
            <div class="mx-auto max-w-7xl space-y-2 px-4 pt-4 sm:px-6 lg:px-8">
                @if (session('status'))
                    <x-alert type="info">{{ session('status') }}</x-alert>
                @endif
                @if (session('success'))
                    <x-alert type="success">{{ session('success') }}</x-alert>
                @endif
                @if (session('error'))
                    <x-alert type="error">{{ session('error') }}</x-alert>
                @endif
            </div>
        @endif

        {{ $slot }}
    </main>

    @include('layouts.footer')

    @stack('scripts')
</body>
</html>
