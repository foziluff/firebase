<?php

namespace Foziluff\Firebase\Facades;

use Foziluff\Firebase\Services\FirebasePushService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, mixed> sendPush(string $token, string $title, string $body, array<string, mixed> $data = [], ?string $sound = null)
 * @method static array<string, mixed> sendToTopic(string $topic, string $title, string $body, array<string, mixed> $data = [], ?string $sound = null)
 *
 * @see FirebasePushService
 */
class FirebasePush extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FirebasePushService::class;
    }
}
