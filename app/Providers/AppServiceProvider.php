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
        // ローカル環境でのhttps化
        if (env('APP_ENV') === 'local') { // 環境に応じて設定
            URL::forceScheme('https');
        }
    }
}
