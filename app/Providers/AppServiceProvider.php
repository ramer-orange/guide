<?php

namespace App\Providers;

use App\Filesystem\R2DiskFactory;
use Illuminate\Support\Facades\Storage;
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
        Storage::extend('r2', fn ($app, array $config) => app(R2DiskFactory::class)->make($config));

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
