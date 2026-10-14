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

        $appUrl = config('app.url');

        if (filled($appUrl)) {
            URL::forceRootUrl($appUrl);

            $scheme = parse_url($appUrl, PHP_URL_SCHEME);

            if (in_array($scheme, ['http', 'https'], true)) {
                URL::forceScheme($scheme);
            }
        }
    }
}
