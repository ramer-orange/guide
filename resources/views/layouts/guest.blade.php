<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">

    <!-- Scripts -->
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
<body class="font-sans text-gray-900 antialiased">
<x-header/>

<main>
    <div class="flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white overflow-hidden sm:rounded-lg">
            {{ $slot }}
        </div>
    </div>
</main>

<x-footer/>
</body>
</html>
