<?php
// File: app/Filament/Resources/BlackFridayResource/Widgets/BlackFridayChartWidget.php

namespace App\Filament\Admin\Resources\BlackFridayResource\Widgets;

use App\Models\BlackFridayProduct;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class BlackFridayChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Black Friday Products by Brand';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $brandData = BlackFridayProduct::where('is_active', true)
            ->selectRaw('brand, COUNT(*) as count')
            ->groupBy('brand')
            ->orderBy('count', 'desc')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Products Count',
                    'data' => $brandData->pluck('count')->toArray(),
                    'backgroundColor' => [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                        '#FF6384',
                        '#C9CBCF'
                    ],
                ]
            ],
            'labels' => $brandData->pluck('brand')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}