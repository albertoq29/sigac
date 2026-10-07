<?php

namespace App\Providers;

use App\Models\AnioEscolar;
use App\Models\User;
use App\Support\ContextoAnio;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Gate::define('control', fn (User $user) => $user->esControl());

        View::composer('components.layouts.app', function ($view) {
            $view->with([
                'anioContexto' => ContextoAnio::anio(),
                'aniosEscolares' => AnioEscolar::query()->recientes()->get(),
            ]);
        });
    }
}
