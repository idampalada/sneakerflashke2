<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\PromoSpreadsheetService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\GineeClient::class, function () {
            return new \App\Services\GineeClient(); // config diambil dari config/services.php
        });
        
        // Daftarkan layanan PromoSpreadsheetService
        $this->app->singleton(PromoSpreadsheetService::class, function ($app) {
            return new PromoSpreadsheetService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Always force HTTPS for this domain
        URL::forceScheme('https');
        
        // Set trusted proxies for load balancer/cloudflare
        $this->app['request']->setTrustedProxies(['*'], 
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // Force HTTPS for asset URLs as well
        if (isset($_SERVER['HTTPS']) || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', 'on');
        }

        // Register Order Observer untuk auto-sync user spending
        Order::observe(OrderObserver::class);

        \Filament\Facades\Filament::serving(function () {
            //
        });
    }
}