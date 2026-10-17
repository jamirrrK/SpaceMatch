<?php

namespace App\Providers;

use App\Services\SmsOtpSenderInterface;
use App\Services\UnavailableSmsOtpSender;
use App\Notifications\InquiryActivityNotification;
use App\Notifications\ViewingActivityNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        View::composer('layouts.app', function ($view): void {
            $user = Auth::user();
            $unreadInquiryCount = $user && in_array($user->role, ['renter', 'owner'], true)
                ? $user->unreadNotifications()->where('type', InquiryActivityNotification::class)->count()
                : 0;
            $unreadViewingCount = $user && in_array($user->role, ['renter', 'owner'], true)
                ? $user->unreadNotifications()->where('type', ViewingActivityNotification::class)->count()
                : 0;

            $view->with(compact('unreadInquiryCount', 'unreadViewingCount'));
        });
    }
}
