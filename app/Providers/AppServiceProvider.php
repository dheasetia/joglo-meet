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
        // Pengirim WhatsApp sesuai config/whatsapp.php (fonnte | log)
        $this->app->bind(\App\Services\WhatsApp\Gateway::class, fn () => match (config('whatsapp.driver')) {
            'fonnte' => new \App\Services\WhatsApp\FonnteGateway(
                config('whatsapp.fonnte.token'), config('whatsapp.fonnte.url'), (int) config('whatsapp.fonnte.timeout'),
            ),
            default => new \App\Services\WhatsApp\LogGateway,
        });
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
