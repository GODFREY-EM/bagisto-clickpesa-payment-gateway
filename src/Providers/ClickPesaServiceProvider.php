<?php

namespace Webkul\ClickPesa\Providers;

use Illuminate\Support\ServiceProvider;

class ClickPesaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'clickpesa');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'clickpesa');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../Resources/views' => resource_path('views/vendor/clickpesa'),
            ], 'clickpesa-views');

            $this->publishes([
                __DIR__ . '/../Config/system.php' => config_path('system.php'),
                __DIR__ . '/../Config/paymentmethods.php' => config_path('paymentmethods.php'),
                __DIR__ . '/../Config/clickpesa.php' => config_path('clickpesa.php'),
            ], 'clickpesa-config');

            $this->publishes([
                __DIR__ . '/../Resources/lang' => resource_path('lang/vendor/clickpesa'),
            ], 'clickpesa-lang');

            $this->publishes([
                __DIR__ . '/../../publishable/assets' => public_path('vendor/clickpesa'),
            ], 'clickpesa-assets');
        }
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        $this->registerConfig();

        // Register helper (optional but useful)
        $this->app->singleton(\Webkul\ClickPesa\Lib\ClickPesaHelper::class, function ($app) {
            return new \Webkul\ClickPesa\Lib\ClickPesaHelper();
        });
    }

    /**
     * Register package config.
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/paymentmethods.php',
            'payment_methods'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/system.php',
            'core'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/clickpesa.php',
            'clickpesa'
        );
    }
}
