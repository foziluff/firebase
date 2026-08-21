<?php

namespace Foziluff\Firebase\Facades;

use Foziluff\Firebase\Services\FirebasePushService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Foziluff\Firebase\Services\FirebaseMessageBuilder toToken(string $token)
 * @method static \Foziluff\Firebase\Services\FirebaseMessageBuilder toTopic(string $topic)
 * @method static array<string, mixed> sendRaw(array<string, mixed> $messagePayload)
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
