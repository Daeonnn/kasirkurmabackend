<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\KasirOnly;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Daftarkan middleware di sini
        Route::aliasMiddleware('checkrole', CheckRole::class);  // Middleware CheckRole
        Route::aliasMiddleware('admin', AdminOnly::class);      // Middleware AdminOnly
        Route::aliasMiddleware('kasir', KasirOnly::class);      // Middleware KasirOnly
    }

    public function register()
    {
        //
    }
}
