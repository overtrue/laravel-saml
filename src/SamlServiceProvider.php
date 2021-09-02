<?php

namespace Overtrue\LaravelSaml;

use Illuminate\Support\ServiceProvider;

class SamlServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot()
    {
        $this->publishes([
            \dirname(__DIR__) . '/config/' => config_path('saml.php'),
        ], 'saml-config');
    }

    public function register()
    {
//        $this->app->singleton(Package::class, function(){
//            return new Package();
//        });
    }
}
