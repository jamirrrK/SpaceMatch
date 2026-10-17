<?php

namespace App\Providers;

use App\Services\SmsOtpSenderInterface;
use App\Services\UnavailableSmsOtpSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsOtpSenderInterface::class, UnavailableSmsOtpSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
