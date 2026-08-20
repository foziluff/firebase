<?php

namespace Foziluff\Firebase\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebasePushService
{
    private const AUTH_URL = 'https://oauth2.googleapis.com/token';

    private const FCM_API_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    private const CACHE_KEY = 'foziluff_firebase_access_token';

    private string $credentials;

    /** @var array<string, mixed>|null */
    private ?array $parsedCredentials = null;

    public function __construct(string $credentials)
    {
        $this->credentials = $credentials;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sendToToken(string $token, ?string $title, string $body, array $data = [], ?string $sound = null, ?string $image = null): array
    {
        return $this->send($this->buildMessage(['token' => $token], $title, $body, $data, $sound, $image));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sendToTopic(string $topic, ?string $title, string $body, array $data = [], ?string $sound = null, ?string $image = null): array
    {
        return $this->send($this->buildMessage(['topic' => $topic], $title, $body, $data, $sound, $image));
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildMessage(array $target, ?string $title, string $body, array $data = [], ?string $sound = null, ?string $image = null): array
    {
        $message = $target;

        $message['notification'] = [
            'body' => $body,
        ];

        if ($title !== null) {
            $message['notification']['title'] = $title;
        }

        if ($image !== null) {
            $message['notification']['image'] = $image;
        }

        if (! empty($data)) {
            $message['data'] = $this->prepareData($data);
        }

        if ($sound !== null) {
            $message['android'] = [
                'notification' => [
                    'sound' => $sound,
                ],
            ];
            $message['apns'] = [
                'payload' => [
                    'aps' => [
                        'sound' => $sound,
                    ],
                ],
            ];
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function prepareData(array $data): array
    {
        $prepared = [];
        foreach ($data as $key => $value) {
            $prepared[(string) $key] = (string) $value;
        }

        return $prepared;
    }

    /**
     * @param  array<string, mixed>  $messagePayload
     * @return array<string, mixed>
     */
    private function send(array $messagePayload): array
    {
        $credentials = $this->getCredentials();
        $projectId = $credentials['project_id'] ?? '';

        if (empty($projectId)) {
            throw new RuntimeException('Firebase project ID is not found in credentials.');
        }

        $url = sprintf(self::FCM_API_URL, $projectId);

        $sendRequest = fn (string $token) => Http::withToken($token)->post($url, ['message' => $messagePayload]);

        $response = $sendRequest($this->getAccessToken($credentials));

        if ($response->status() === 401) {
            Cache::forget(self::CACHE_KEY);
            $response = $sendRequest($this->getAccessToken($credentials));
        }

        $response->throwIf($response->serverError());

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function getAccessToken(array $credentials): string
    {
        $cachedToken = Cache::get(self::CACHE_KEY);
        if ($cachedToken) {
            return $cachedToken;
        }

        $jwt = $this->generateJwt($credentials);

        $response = Http::asForm()->post(self::AUTH_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        $response->throw();

        $data = $response->json();
        $token = $data['access_token'];
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        Cache::put(self::CACHE_KEY, $token, max(1, $expiresIn - 10));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function generateJwt(array $credentials): string
    {
        if (empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Invalid Firebase credentials file. Missing client_email or private_key.');
        }

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $now = time();
        $payload = [
            'iss' => $credentials['client_email'],
            'sub' => $credentials['client_email'],
            'aud' => self::AUTH_URL,
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $dataToSign = $headerEncoded.'.'.$payloadEncoded;

        if (! openssl_sign($dataToSign, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign JWT with the provided private key.');
        }

        $signatureEncoded = $this->base64UrlEncode($signature);

        return $dataToSign.'.'.$signatureEncoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * @return array<string, mixed>
     */
    private function getCredentials(): array
    {
        if ($this->parsedCredentials !== null) {
            return $this->parsedCredentials;
        }

        $credentialsString = trim($this->credentials);

        if (str_starts_with($credentialsString, '{') && str_ends_with($credentialsString, '}')) {
            return $this->parsedCredentials = json_decode($credentialsString, true, 512, JSON_THROW_ON_ERROR);
        }

        $content = @file_get_contents($this->credentials);
        if ($content === false) {
            throw new RuntimeException("Failed to read Firebase credentials file from: {$this->credentials}");
        }

        return $this->parsedCredentials = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
