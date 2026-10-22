<?php

namespace App\Providers;

use App\Tenancy\CurrentTenant;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One CurrentTenant per request (and per queued job), never shared.
        $this->app->scoped(CurrentTenant::class);
    }

    public function boot(): void
    {
        //
    }
}
