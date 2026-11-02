<?php

namespace App\Providers;

use App\Models\Message;
use App\Models\Transaction;
use App\Models\VipRequest;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory)->create(
                new Dsn('brevo+api', 'default', (string) config('services.brevo.key'))
            );
        });

        // Badges du menu admin : demandes à traiter et messages non lus.
        View::composer('admin.layout', function ($view) {
            $view->with('navCounts', [
                'transactions' => Transaction::query()->whereIn('status', ['pending', 'processing'])->count(),
                'messages' => Message::query()->where('sender', 'user')->where('is_read', false)->count(),
                'vip' => VipRequest::query()->usable()->count(),
            ]);
        });
    }
}
