<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark dot-roboto">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        {{-- Roboto, self-hosted (dot-ui): the ecosystem's one typeface. No external font. --}}
        <link rel="preload" href="{{ asset('dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
        <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-roboto.min.css') }}">
        <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-type.min.css') }}">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/css/guest.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body>
        <div class="font-sans antialiased">
            {{ $slot }}
        </div>

        @livewireScripts
    </body>
</html>
