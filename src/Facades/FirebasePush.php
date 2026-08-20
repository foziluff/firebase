<?php

namespace Foziluff\Firebase\Facades;

use Illuminate\Support\Facades\Facade;
use Foziluff\Firebase\Services\FirebasePushService;

/**
 * @method static array sendPush(string $token, string $title, string $body, array $data = [])
 * @method static array sendToTopic(string $topic, string $title, string $body, array $data = [])
 *
 * @see \Foziluff\Firebase\Services\FirebasePushService
 */
class FirebasePush extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FirebasePushService::class;
    }
}
