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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            try {
                $totalTrash = \Illuminate\Support\Facades\DB::table('trash')->whereNull('restored_at')->count();
                $view->with('sidebarTotalTrash', $totalTrash);
            } catch (\Throwable $e) {
                $view->with('sidebarTotalTrash', 0);
            }
        });
    }
}
