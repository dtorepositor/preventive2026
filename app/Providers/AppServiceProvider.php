<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $appPath = trim((string) parse_url($appUrl, PHP_URL_PATH), '/');

        if (! $this->app->runningInConsole() && $appPath !== '') {
            $request = $this->app['request'];

            URL::forceRootUrl(rtrim($request->getSchemeAndHttpHost() . '/' . $appPath, '/'));
        }
    }
}
