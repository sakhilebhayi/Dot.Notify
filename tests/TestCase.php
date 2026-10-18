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
