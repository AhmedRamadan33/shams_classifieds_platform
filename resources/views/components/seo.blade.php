@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'robots' => 'index,follow',
    'image' => null,
    'ogType' => 'website',
    'jsonLd' => [],
])
@php
    $brand = __('app.brand');
    $fullTitle = $title ? $title.' | '.$brand : $brand.' — '.__('app.tagline');
    $metaDescription = $description ?: __('app.meta_description');
    $canonicalUrl = $canonical ?: url()->current();
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonicalUrl }}">

<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#c2410c">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $brand }}">
<link rel="icon" href="/icons/icon-192.png" sizes="192x192">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

<meta property="og:site_name" content="{{ $brand }}">
<meta property="og:locale" content="ar_AR">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $title ?: $brand }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
@if ($image)
    <meta property="og:image" content="{{ $image }}">
@endif
<meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $title ?: $brand }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
@if ($image)
    <meta name="twitter:image" content="{{ $image }}">
@endif

@foreach ($jsonLd as $block)
    <script type="application/ld+json">@json($block, $jsonFlags)</script>
@endforeach
