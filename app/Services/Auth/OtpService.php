<?php

namespace App\Services\Auth;

use App\Contracts\SmsGateway;
use App\Models\OtpCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Issues and checks one-time passcodes sent by SMS.
 *
 * Codes are stored hashed, expire, are single-use, and lock after a fixed
 * number of wrong guesses. Issuing a new code voids older ones.
 */
class OtpService
{
    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_REGISTER = 'register';

    public const PURPOSE_CONFIRM = 'confirm';

    public function __construct(private readonly SmsGateway $sms) {}

    /**
     * Create a code and send it. Returns the plain code so local/dev tooling can surface it.
     */
    public function issue(string $phone, string $purpose, ?string $ip = null): string
    {
        $code = $this->generate();

        DB::transaction(function () use ($phone, $purpose, $code, $ip) {
            OtpCode::query()
                ->where('phone', $phone)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            OtpCode::query()->create([
                'phone' => $phone,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes((int) config('napps.otp.ttl_minutes')),
                'ip_address' => $ip,
            ]);
        });

        $this->sms->send($phone, __(':app code: :code. It expires in :minutes minutes. Do not share it with anyone.', [
            'app' => config('napps.sms.brand'),
            'code' => $code,
            'minutes' => config('napps.otp.ttl_minutes'),
        ]), transactional: true);

        return $code;
    }

    public function verify(string $phone, string $purpose, string $code): bool
    {
        $otp = OtpCode::query()
            ->usable()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (! $otp || $otp->attempts >= (int) config('napps.otp.max_attempts')) {
            return false;
        }

        if (! Hash::check(trim($code), $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        return true;
    }

    private function generate(): string
    {
        $length = (int) config('napps.otp.length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }
}
