<?php

namespace App\Providers;

use App\Models\Company;
use App\Policies\CompanyPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Company::class, CompanyPolicy::class);
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(20)->by('ip:'.$r->ip()),
            Limit::perMinute(5)->by('account:'.hash('sha256', strtolower(trim((string) $r->input('username'))))),
        ]);
    }
}
