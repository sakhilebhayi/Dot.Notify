<?php

namespace Tests;

use App\Services\Notify\UrlGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Collection;
use Symfony\Component\Mailer\SentMessage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // The shared sign-in package's own suite (packages/dot-sso-client/tests) opens /tasks/N deep links; this app has no
        // such page, so the test run (only) allows that one path in addition to the app's real allowlist.
        config(['dotsso.deep_link.allow' => [...(array) config('dotsso.deep_link.allow', []), '#^/tasks(/|\?|$)#']]);

        // The package suite posts to a stand-in /webhooks/stripe; here /webhooks/{token} is the app's real HMAC receiver and
        // would match first (401). Keep that one stand-in path free in the test run; the real receiver is proven untouched by
        // Tests\\Feature\\Sso\\CentralSignInNotifyTest.
        foreach ($this->app['router']->getRoutes() as $route) {
            if ($route->getName() === 'webhooks.receive') {
                $route->where('token', '^(?!stripe$).+');
            }
        }

        // Outbound channel HTTP resolves hostnames before sending (SSRF
        // guard). Tests never touch real DNS: every hostname resolves to a
        // public documentation-range address unless a test rebinds this.
        $this->app->singleton(UrlGuard::class, fn () => new UrlGuard(fn (string $host): array => ['93.184.216.34']));
    }

    /**
     * Mail::fake()'s raw() is a no-op, so tests that need to see what was
     * actually "sent" read the in-memory array transport (MAIL_MAILER=array).
     * Nothing leaves the process.
     *
     * @return Collection<int, SentMessage>
     */
    protected function sentMessages(): Collection
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }
}
