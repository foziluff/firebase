<?php

namespace Foziluff\Firebase;

use Foziluff\Firebase\Services\FirebasePushService;
use Illuminate\Support\ServiceProvider;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/firebase.php', 'firebase');

        $this->app->singleton(FirebasePushService::class, function ($app) {
            return new FirebasePushService(
                $app['config']->get('firebase.credentials'),
                $app['config']->get('firebase.project_id')
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/firebase.php' => $this->app->basePath('config/firebase.php'),
            ], 'firebase-config');
        }
    }
}
