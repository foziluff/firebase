<?php

namespace Foziluff\Firebase;

use Foziluff\Firebase\Services\FirebasePushService;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FirebasePushService::class, function ($app) {
            $credentials = env('FIREBASE_CREDENTIALS');

            if (empty($credentials)) {
                throw new RuntimeException('FIREBASE_CREDENTIALS environment variable is not set.');
            }

            if (! str_starts_with($credentials, '{') && ! str_starts_with($credentials, '/')) {
                $credentials = $app->basePath($credentials);
            }

            return new FirebasePushService($credentials);
        });
    }

    public function boot(): void {}
}
