<?php

namespace App\Contracts;

interface SmsGateway
{
    /**
     * Send a plain-text SMS to an E.164 number.
     *
     * @param  bool  $transactional  OTPs and urgent notices use the DND-bypassing route.
     */
    public function send(string $to, string $message, bool $transactional = false): void;
}
