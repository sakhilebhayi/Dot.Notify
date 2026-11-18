{{-- R-54: every sign-in failure ends here, in words, with one next step and no automatic redirect. --}}
<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="sso-claim" role="alert">
            <h1 class="font-display sso-title">{{ $headline }}</h1>
            <p class="sso-lede">{{ $body }}</p>
            <div class="sso-actions">
                @if ($retry)<a class="sso-submit sso-btn" href="{{ $retryUrl }}">Start again</a>@endif
                <a class="sso-submit sso-btn{{ $retry ? ' sso-btn--quiet' : '' }}" href="{{ $homeUrl }}">Back to Dot.Notify</a>
            </div>
            <p class="sso-help">Reference: {{ $code }}</p>
        </div>
    </x-authentication-card>
</x-guest-layout>
