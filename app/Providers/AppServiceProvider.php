<?php

namespace App\Providers;

use App\Services\MediaUsageRegistry;
use App\Services\ThemeService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MediaUsageRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // As cores do tema chegam ao layout público por um composer específico,
        // e não por View::share: o painel administrativo usa outro layout e
        // permanece neutro nesta subfase, por decisão arquitetural.
        View::composer('layouts.app', function (ViewInstance $view): void {
            $view->with('themeColors', $this->app->make(ThemeService::class)->colors());
        });
    }
}
