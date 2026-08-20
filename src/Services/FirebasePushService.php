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

    public function __construct(string $credentials)
    {
        $this->credentials = $credentials;
    }

    public function sendPush(string $token, string $title, string $body, array $data = []): array
    {
        $message = [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ];

        if (! empty($data)) {
            $message['data'] = $this->prepareData($data);
        }

        return $this->send($message);
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        $topic = str_starts_with($topic, '/topics/') ? $topic : '/topics/'.$topic;

        $message = [
            'topic' => $topic,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ];

        if (! empty($data)) {
            $message['data'] = $this->prepareData($data);
        }

        return $this->send($message);
    }

    private function prepareData(array $data): array
    {
        $prepared = [];
        foreach ($data as $key => $value) {
            $prepared[(string) $key] = (string) $value;
        }

        return $prepared;
    }

    private function send(array $messagePayload): array
    {
        $credentials = $this->getCredentials();
        $projectId = $credentials['project_id'] ?? '';

        if (empty($projectId)) {
            throw new RuntimeException('Firebase project ID is not found in credentials.');
        }

        $accessToken = $this->getAccessToken($credentials);

        $url = sprintf(self::FCM_API_URL, $projectId);

        $response = Http::withToken($accessToken)
            ->post($url, [
                'message' => $messagePayload,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('FCM send failed: '.$response->body());
        }

        return $response->json();
    }

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

        if (! $response->successful()) {
            throw new RuntimeException('Failed to obtain Firebase access token: '.$response->body());
        }

        $data = $response->json();
        $token = $data['access_token'];
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        Cache::put(self::CACHE_KEY, $token, max(1, $expiresIn - 10));

        return $token;
    }

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

        $base64UrlEncode = fn ($data) => str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));

        $headerEncoded = $base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $dataToSign = $headerEncoded.'.'.$payloadEncoded;

        if (! openssl_sign($dataToSign, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign JWT with the provided private key.');
        }

        $signatureEncoded = $base64UrlEncode($signature);

        return $dataToSign.'.'.$signatureEncoded;
    }

    private function getCredentials(): array
    {
        $credentialsString = trim($this->credentials);

        if (str_starts_with($credentialsString, '{') && str_ends_with($credentialsString, '}')) {
            return json_decode($credentialsString, true, 512, JSON_THROW_ON_ERROR);
        }

        if (! file_exists($this->credentials)) {
            throw new RuntimeException("Firebase credentials file not found at: {$this->credentials}");
        }

        $content = file_get_contents($this->credentials);
        if ($content === false) {
            throw new RuntimeException('Failed to read Firebase credentials file.');
        }

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
