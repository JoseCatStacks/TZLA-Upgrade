<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Phone-on-WiFi and other LAN hosts: generate asset/nav URLs from the
        // address the browser actually used, not APP_URL (which is 127.0.0.1).
        if (! $this->app->runningInConsole()) {
            URL::forceRootUrl(request()->getSchemeAndHttpHost());
        }

        // Per-wallet limiter for stake/unstake write operations (5 per minute per wallet).
        // Layered on top of the existing per-IP throttle:60,1 group limiter.
        RateLimiter::for('staking-writes', function (Request $request) {
            $wallet = (string) ($request->input('wallet') ?? '');

            return $wallet !== ''
                ? Limit::perMinute(5)->by('staking-write:' . $wallet)
                : Limit::perMinute(5)->by('staking-write-ip:' . $request->ip());
        });

        // Protect the free-tier Helius key behind the RPC proxy.
        RateLimiter::for('rpc', function (Request $request) {
            return Limit::perMinute(60)->by('rpc:' . $request->ip());
        });
    }
}
