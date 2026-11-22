<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Development/testing gateway: writes messages to the log instead of sending.
 */
class LogSmsGateway implements SmsGateway
{
    /** @var list<array{to: string, message: string, transactional: bool}> */
    public array $sent = [];

    public function send(string $to, string $message, bool $transactional = false): void
    {
        $this->sent[] = compact('to', 'message', 'transactional');

        Log::channel(config('napps.sms.log_channel'))->info('[sms] '.$to.': '.$message);
    }
}
