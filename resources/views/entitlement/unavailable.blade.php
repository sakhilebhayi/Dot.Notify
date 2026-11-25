{{-- R-61: InfoDot has switched Dot.Notify off (maintenance or unavailable). Same gate page as the refusal, with no
     state slip; ONE action (data-primary-action). Rendered by App\Support\EntitlementRefusal with HTTP 503. It shows
     nothing from the desk. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dot-roboto">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Dot.Notify</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <script src="{{ asset('js/notify-shell.js') }}"></script>
    <link rel="preload" href="{{ asset('dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-roboto.min.css') }}">
    <link rel="stylesheet" href="{{ asset('dot-ui/css/dot-type.min.css') }}">
    @vite(['resources/css/notify.css'])
</head>
<body class="nf-gate-page">
    <main class="nf-gate">
        <header class="nf-gate-top">
            <img class="nf-gate-logo nf-logo-light" src="{{ asset('images/logo.png') }}" alt="Dot.Notify" width="1181" height="886">
            <img class="nf-gate-logo nf-logo-dark" src="{{ asset('images/logo-light.png') }}" alt="Dot.Notify" width="1181" height="886">
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nf-linkbtn">Sign out</button>
                </form>
            @endauth
        </header>

        <section class="nf-slip nf-gate-slip" aria-labelledby="gate-title">
            <h1 class="nf-gate-title" id="gate-title">{{ $title }}</h1>
            <p class="nf-gate-body">{{ $body }}</p>
            <a class="nf-primary" href="{{ $platformsUrl }}" data-primary-action>Open Platforms in InfoDot</a>
        </section>
    </main>
</body>
</html>
