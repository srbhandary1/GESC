<?php

declare(strict_types=1);

namespace App\Providers;

use App\Health\DatabaseReadinessProbe;
use App\Health\ReadinessChecker;
use App\Health\RedisReadinessProbe;
use App\Observability\HttpRequestLogger;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            ReadinessChecker::class,
            fn (): ReadinessChecker => new ReadinessChecker(
                $this->app->make(DatabaseReadinessProbe::class),
                $this->app->make(RedisReadinessProbe::class),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            RequestHandled::class,
            fn (RequestHandled $event) => $this->app
                ->make(HttpRequestLogger::class)
                ->handle($event),
        );
    }
}
