<?php

namespace Foziluff\Firebase\Services;

class FirebaseMessageBuilder
{
    private FirebasePushService $service;

    /** @var array<string, mixed> */
    private array $target;

    private ?string $title = null;

    private ?string $body = null;

    /** @var array<string, mixed> */
    private array $data = [];

    private ?string $sound = null;

    private ?string $image = null;

    /**
     * @param  array<string, mixed>  $target
     */
    public function __construct(FirebasePushService $service, array $target)
    {
        $this->service = $service;
        $this->target = $target;
    }

    public function withTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function withBody(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function withData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function withSound(?string $sound = 'default'): self
    {
        $this->sound = $sound;

        return $this;
    }

    public function withImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function send(): array
    {
        $message = $this->target;

        if ($this->body !== null) {
            $message['notification']['body'] = $this->body;
        }

        if ($this->title !== null) {
            $message['notification']['title'] = $this->title;
        }

        if ($this->image !== null) {
            $message['notification']['image'] = $this->image;
        }

        if (! empty($this->data)) {
            $message['data'] = $this->prepareData($this->data);
        }

        if ($this->sound !== null) {
            $message['android'] = [
                'notification' => [
                    'sound' => $this->sound,
                ],
            ];
            $message['apns'] = [
                'payload' => [
                    'aps' => [
                        'sound' => $this->sound,
                    ],
                ],
            ];
        }

        return $this->service->sendRaw($message);
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
}
