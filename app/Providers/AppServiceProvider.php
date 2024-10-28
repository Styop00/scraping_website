<?php

namespace App\Providers;

use App\Services\Scraping\Client as ScrapingClient;
use App\Services\GoogleAPI\Client as GoogleAPIClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('scrapingClient', function () {
            return new ScrapingClient();
        });
        $this->app->singleton('googleClient', function () {
            return new GoogleAPIClient();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
