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

    private string $credentialsPath;
    private string $projectId;

    public function __construct(string $credentialsPath, string $projectId = '')
    {
        $this->credentialsPath = $credentialsPath;
        $this->projectId = $projectId;
    }

    /**
     * Send a Push Notification to a specific device token.
     *
     * @param string $token The FCM registration token of the device.
     * @param string $title The notification title.
     * @param string $body The notification body text.
     * @param array<string, mixed> $data Extra data payload (will be converted to strings automatically).
     * @return array<string, mixed> Response from FCM.
     */
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

    /**
     * Send a Push Notification to a topic.
     *
     * @param string $topic The topic name (e.g. "news").
     * @param string $title The notification title.
     * @param string $body The notification body text.
     * @param array<string, mixed> $data Extra data payload (will be converted to strings automatically).
     * @return array<string, mixed> Response from FCM.
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        // Topic can be passed with or without /topics/ prefix
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

    /**
     * FCM data payloads strictly require string values.
     * This helper automatically converts any scalar values to strings.
     *
     * @param array<string, mixed> $data
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
     * @param array<string, mixed> $messagePayload
     * @return array<string, mixed>
     */
    private function send(array $messagePayload): array
    {
        $credentials = $this->getCredentials();
        $projectId = $this->projectId ?: ($credentials['project_id'] ?? '');

        if (empty($projectId)) {
            throw new RuntimeException("Firebase project ID is not configured and not found in credentials.");
        }

        $accessToken = $this->getAccessToken($credentials);

        $url = sprintf(self::FCM_API_URL, $projectId);

        $response = Http::withToken($accessToken)
            ->post($url, [
                'message' => $messagePayload
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("FCM send failed: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Gets a valid OAuth2 Access Token for FCM.
     * Uses Laravel Cache to reuse the token for its lifetime (usually 1 hour).
     *
     * @param array<string, mixed> $credentials
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

        if (! $response->successful()) {
            throw new RuntimeException("Failed to obtain Firebase access token: " . $response->body());
        }

        $data = $response->json();
        $token = $data['access_token'];
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        // Cache the token, leaving a 10-second leeway to prevent expiration in-flight
        Cache::put(self::CACHE_KEY, $token, max(1, $expiresIn - 10));

        return $token;
    }

    /**
     * Generates a signed JWT using the Service Account's private RSA key.
     *
     * @param array<string, mixed> $credentials
     */
    private function generateJwt(array $credentials): string
    {
        if (empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException("Invalid Firebase credentials file. Missing client_email or private_key.");
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

        $base64UrlEncode = fn($data) => str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));

        $headerEncoded = $base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $dataToSign = $headerEncoded . '.' . $payloadEncoded;

        if (! openssl_sign($dataToSign, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException("Failed to sign JWT with the provided private key.");
        }

        $signatureEncoded = $base64UrlEncode($signature);

        return $dataToSign . '.' . $signatureEncoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCredentials(): array
    {
        if (! file_exists($this->credentialsPath)) {
            throw new RuntimeException("Firebase credentials file not found at: {$this->credentialsPath}");
        }

        $content = file_get_contents($this->credentialsPath);
        if ($content === false) {
            throw new RuntimeException("Failed to read Firebase credentials file.");
        }

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
