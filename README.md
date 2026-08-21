# Firebase Cloud Messaging for Laravel

Lightweight, high-performance, and secure Laravel package for sending Firebase Cloud Messaging (FCM) push notifications using the new HTTP v1 API.

This package is built with Senior-level optimizations in mind: it generates JWTs locally (no need for bloated external Google API clients), uses in-memory caching to prevent TOCTOU race conditions and reduce Disk I/O, and automatically handles auto-retries for expired authentication tokens.

## Features

- **Blazing Fast**: JWTs are generated and signed locally using OpenSSL.
- **In-Memory Caching**: JSON credentials are parsed and cached in RAM for the duration of the request lifecycle, ensuring maximum throughput for bulk notifications.
- **FCM HTTP v1 API**: Fully supports the modern Firebase API.
- **Smart Retries**: Automatically refreshes the OAuth2 token and retries if Google responds with a `401 Unauthorized`.
- **Zero Config Bloat**: No need to run `php artisan vendor:publish`. Everything is configured via a single `.env` variable.
- **Silent Pushes & Custom Sounds**: First-class support for data-only notifications and platform-specific sound payloads (iOS APNs and Android).

## Requirements

- PHP 8.2+
- Laravel 11.x (or higher)

## Installation

Install the package via Composer:

```bash
composer require foziluff/firebase
```

## Configuration

You do not need to publish any configuration files. The package intelligently configures itself from your `.env` file.

Add the `FIREBASE_CREDENTIALS` variable to your `.env` file. You can configure it in one of two ways:

### Option 1: File Path (Recommended)
Place your Firebase Service Account JSON file (e.g., `firebase.json`) in the root of your Laravel project and reference it:

```env
FIREBASE_CREDENTIALS=firebase.json
```
*(The package automatically resolves relative paths using Laravel's `base_path()`, so it works perfectly in both web requests and artisan queues).*

### Option 2: Raw JSON String
If you prefer not to store files on the server (e.g., in Docker/Kubernetes environments), you can pass the raw JSON string directly:

```env
FIREBASE_CREDENTIALS='{"type": "service_account", "project_id": "your-project", "private_key": "-----BEGIN PRIVATE KEY-----\n...\n", "client_email": "..."}'
```

## Usage

Use the provided `FirebasePush` facade to send notifications using its clean Fluent Builder interface.

### Send to a Specific Device Token

```php
use Foziluff\Firebase\Facades\FirebasePush;

// Standard notification
FirebasePush::toToken($token)
    ->withTitle('Hello World')
    ->withBody('This is a test notification.')
    ->send();

// With custom data payload, sound, and image
FirebasePush::toToken($token)
    ->withTitle('Order Shipped')
    ->withBody('Your order #1234 is on the way!')
    ->withData(['order_id' => 1234])
    ->withSound()
    ->withImage('https://example.com/images/promo.jpg')
    ->send();
```

### Send to a Topic

The package automatically prefixes the topic with `/topics/` if you forget it.

```php
use Foziluff\Firebase\Facades\FirebasePush;

FirebasePush::toTopic('news') // or '/topics/news'
    ->withTitle('Breaking News')
    ->withBody('Laravel is awesome!')
    ->withData(['article_id' => 42])
    ->send();
```

### Silent / Data-Only Pushes

To send a push notification that doesn't trigger a visual alert on the device but wakes up the app in the background, simply omit the title and body:

```php
FirebasePush::toToken($token)
    ->withData(['action' => 'sync_data'])
    ->send();
```

### Advanced Usage (Raw JSON)

If you need to send highly specific FCM parameters (like `webpush` configs or custom `android` properties) that aren't available in the builder, you can pass a raw payload array directly:

```php
FirebasePush::sendRaw([
    'token' => $token,
    'notification' => [
        'title' => 'Custom Push'
    ],
    'android' => [
        'priority' => 'high'
    ]
]);
```

## Error Handling

Unlike standard implementations that throw an exception and crash your loops on invalid device tokens (`404 Not Found` or `400 Bad Request`), this package gracefully returns the Google API error array. 

This allows you to safely process bulk notifications and delete invalid tokens from your database without wrapping everything in a `try-catch` block:

```php
$response = FirebasePush::toToken($token)
    ->withTitle('Title')
    ->withBody('Body')
    ->send();

if (isset($response['error'])) {
    // The device token is invalid, unregistered, or malformed.
    // E.g. $response['error']['details'][0]['errorCode'] === 'UNREGISTERED'
    $user->update(['fcm_token' => null]);
}
```

Exceptions are only thrown for critical failures (e.g., Google FCM servers are down with a `5xx` error, or your JSON credentials are invalid).

## License

The MIT License (MIT).
