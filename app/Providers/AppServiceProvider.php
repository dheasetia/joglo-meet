<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('id');
        Model::preventLazyLoading(! $this->app->isProduction());

        // Log aktivitas: catat setiap login berhasil (aplikasi rapat maupun /admin)
        Event::listen(function (Login $event) {
            if (! $event->user instanceof User) {
                return;
            }

            activity('akses')
                ->causedBy($event->user)
                ->event('login')
                ->withProperties(['detail' => ['Alamat IP' => request()->ip()]])
                ->log('masuk ke aplikasi');
        });
    }
}
