<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar o VendedorRepository para injeção de dependência
        $this->app->bind(
            \App\Repositories\Contracts\VendedorRepositoryInterface::class,
            \App\Repositories\Eloquent\VendedorRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
