<?php

namespace App\Providers;

use App\Models\NotifyChannel;
use App\Models\NotifyRule;
use App\Models\NotifySchedule;
use App\Models\NotifyTemplate;
use App\Models\NotifyWebhook;
use App\Policies\NotifyChannelPolicy;
use App\Policies\NotifyRulePolicy;
use App\Policies\NotifySchedulePolicy;
use App\Policies\NotifyTemplatePolicy;
use App\Policies\NotifyWebhookPolicy;
use App\Services\Notify\UrlGuard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UrlGuard::class, fn () => new UrlGuard);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(NotifyChannel::class, NotifyChannelPolicy::class);
        Gate::policy(NotifyRule::class, NotifyRulePolicy::class);
        Gate::policy(NotifyTemplate::class, NotifyTemplatePolicy::class);
        Gate::policy(NotifyWebhook::class, NotifyWebhookPolicy::class);
        Gate::policy(NotifySchedule::class, NotifySchedulePolicy::class);
    }
}
