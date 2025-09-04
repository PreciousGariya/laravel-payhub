<?php
namespace Gokulsingh\LaravelPayhub;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Gokulsingh\LaravelPayhub\Http\Controllers\WebhookController;
class PaymentServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/payment.php', 'payment');
        $this->app->singleton('payment', function () {
            return new Payment();
        });
    }
    public function boot()
    {
        $this->publishes([__DIR__.'/../config/payment.php' => config_path('payment.php')], 'config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'migrations');
        Route::macro('paymentWebhooks', function ($prefix = 'payment/webhook') {
            Route::post("$prefix/{gateway}", [WebhookController::class, 'handle'])->name('payment.webhook');
        });
    }
}

