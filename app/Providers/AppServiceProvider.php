<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        if (app()->environment('local')) {
            URL::forceScheme('https');
            return;
        }

        $appUrl = config('app.url');

        if (filled($appUrl)) {
            URL::forceRootUrl($appUrl);

            if (parse_url($appUrl, PHP_URL_SCHEME) === 'https') {
                URL::forceScheme('https');
            }
        }
    }
}
