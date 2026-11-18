<?php

use App\Sso\NotifyProvisioner;

/*
 * Dot.Notify's own choices for the shared sign-in package (packages/dot-sso-client). Everything else, including
 * DOTSSO_ENABLED (default false), comes from the package's defaults and the host .env.
 */
return [
    'provisioner' => NotifyProvisioner::class,

    // Notify has always created an account for a person InfoDot vouches for (INFODOT_PROVISION_USERS), so that stays.
    'auto_provision' => (bool) env('DOTSSO_AUTO_PROVISION', env('INFODOT_PROVISION_USERS', true)),

    'landing' => '/dashboard',

    // A deep link may open any signed-in page of the app, and nothing else. The ingest API and the inbound webhook
    // receiver are machine doors with their own HMAC and are never a deep link target.
    'deep_link' => [
        'allow' => ['#^/(dashboard|connected-apps|search|user/profile|teams|team-invitations)(/|\?|$)#'],
        'deny' => ['#^/(sso|login|register|logout|forgot-password|reset-password|auth|oauth|two-factor-challenge|user/confirm|webhooks|api)(/|\?|$)#i'],
    ],

    // /api/ingest/events (HMAC), /webhooks/{token} (token + signature), /api/user (Sanctum), /up* are not login pages
    // and the package never touches them.
    'allow_paths' => [],
];
