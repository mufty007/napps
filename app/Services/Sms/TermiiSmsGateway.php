<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Termii SMS API (https://developers.termii.com/messaging-api).
 */
class TermiiSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $senderId,
        private readonly string $baseUrl = 'https://api.ng.termii.com',
    ) {}

    public function send(string $to, string $message, bool $transactional = false): void
    {
        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 500, throw: false)
            ->post('/api/sms/send', [
                'api_key' => $this->apiKey,
                'to' => ltrim($to, '+'),
                'from' => $this->senderId,
                'sms' => $message,
                'type' => 'plain',
                // The "dnd" route reaches numbers on the Do-Not-Disturb list (OTPs, urgent notices).
                'channel' => $transactional ? 'dnd' : 'generic',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Termii SMS failed: HTTP '.$response->status());
        }
    }
}
