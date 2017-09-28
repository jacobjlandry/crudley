<?php

namespace JacobLandry\CRUDley;

use Illuminate\Support\ServiceProvider;
use Storage;

class CRUDleyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/view/CRUDTemplates' => resource_path('views/CRUDTemplates'),
            __DIR__.'/storage/history.php' => storage_path('app/crud/history.php')
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
