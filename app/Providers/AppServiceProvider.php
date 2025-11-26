<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use App\Models\Order;
use App\Models\BlackFridayProduct;
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

        // ✅ TAMBAHAN BARU: Share Black Friday data dengan semua views
        if (class_exists(BlackFridayProduct::class)) {
            View::composer('*', function ($view) {
                try {
                    // Check if there are active Black Friday products
                    $blackFridayActive = BlackFridayProduct::where('is_active', true)
                        ->where(function ($query) {
                            $now = now();
                            $query->where('sale_start_date', '<=', $now)
                                  ->orWhereNull('sale_start_date');
                        })
                        ->where(function ($query) {
                            $now = now();
                            $query->where('sale_end_date', '>=', $now)
                                  ->orWhereNull('sale_end_date');
                        })
                        ->exists();

                    // Get Black Friday stats for navigation
                    $blackFridayStats = [
                        'active' => $blackFridayActive,
                        'total_products' => BlackFridayProduct::where('is_active', true)->count(),
                        'flash_sale_count' => BlackFridayProduct::where('is_flash_sale', true)
                            ->where('is_active', true)
                            ->count(),
                        'max_discount' => BlackFridayProduct::where('is_active', true)
                            ->whereNotNull('discount_percentage')
                            ->max('discount_percentage') ?? 0,
                    ];

                    $view->with('blackFridayStats', $blackFridayStats);
                } catch (\Exception $e) {
                    // Jika tabel belum ada atau ada error, set default values
                    $view->with('blackFridayStats', [
                        'active' => false,
                        'total_products' => 0,
                        'flash_sale_count' => 0,
                        'max_discount' => 0,
                    ]);
                }
            });
        }

        \Filament\Facades\Filament::serving(function () {
            //
        });
    }
}