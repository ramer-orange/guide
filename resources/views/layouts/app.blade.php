<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'しおり')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/ogp/ogp.webp') }}">

    <!-- Twitter -->
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:image" content="{{ asset('images/ogp/ogp.webp') }}">

    <meta name="color-scheme" content="light dark">

    <meta name="google-adsense-account" content="ca-pub-1288717134141444">

    @yield('meta')

    <!-- Fonts -->

    @livewireStyles

    @vite([
    'resources/js/app.js',
    'resources/css/app.css',
    'resources/js/hamburger.js',
    'resources/js/share-button.js',
    'resources/js/validation-scroll.js',
    'resources/js/alert-modal.js',
    ])

    @livewireScripts
</head>
<body>
    <x-header/>

    <main>
        @yield('content')
    </main>

    <x-footer/>
</body>
</html>
