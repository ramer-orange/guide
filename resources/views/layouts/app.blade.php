<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'しおり')</title>

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/ogp/ogp.png') }}">

    <!-- Twitter -->
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:image" content="{{ asset('images/ogp/ogp.png') }}">

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
    'resources/js/accordion.js',
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
