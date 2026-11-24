<?php

namespace App\Providers;

use App\Services\PgEncryptionContext;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(ConnectionEstablished::class, static function (ConnectionEstablished $event): void {
            PgEncryptionContext::apply($event->connection);
        });
    }
}
