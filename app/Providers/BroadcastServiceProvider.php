<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // App auth is JWT (tymon/jwt-auth), not the default session guard —
        // channel authorization needs the same middleware every other
        // protected API route uses so Auth::user() resolves correctly.
        Broadcast::routes([
            'middleware' => ['api', 'jwt.verify'],
            'prefix' => 'api/v1',
        ]);

        require base_path('routes/channels.php');
    }
}
