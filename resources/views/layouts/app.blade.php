<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'しおり')</title>

    <!-- Fonts -->

    @livewireStyles

    @vite(['resources/js/app.js', 'resources/js/hamburger.js', 'resources/css/app.css', 'resources/js/share-button.js'])

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
