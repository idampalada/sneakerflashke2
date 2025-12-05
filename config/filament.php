<?php

return [
    'default' => env('FILAMENT_PANEL', 'admin'),
    
    'panels' => [
        'admin' => [
            'id' => 'admin',
            'path' => '/admin',
            'login' => \Filament\Http\Livewire\Auth\Login::class,
            'auth' => [
                'guard' => env('FILAMENT_AUTH_GUARD', 'web'),
                'provider' => 'users',
            ],
            'middleware' => [
                'web',
                \Filament\Http\Middleware\Authenticate::class,
                \Filament\Http\Middleware\DisableBladeIconComponents::class,
                \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
            ],
            'authMiddleware' => [
                \Filament\Http\Middleware\Authenticate::class,
            ],
            'theme' => null,
            'brand' => env('APP_NAME'),
            'breadcrumbs' => true,
            'navigation' => [
                'groups' => [
                    'enabled' => true,
                    'collapsible' => true,
                ],
            ],
            'widgets' => [
                'account' => \Filament\Widgets\AccountWidget::class,
                'filament-info' => \Filament\Widgets\FilamentInfoWidget::class,
            ],
            'livewire' => [
                'theme' => 'tailwind',
            ],
            'dark_mode' => false,
            'database_notifications' => [
                'enabled' => false,
            ],
            'spa' => false,
            'unsaved_changes_alerts' => true,
        ],
    ],
    
    'livewire' => [
        'theme' => 'tailwind',
    ],
    
    'assets' => [
        'css' => null,
        'js' => null,
    ],
    
    'cache_components' => true,
];