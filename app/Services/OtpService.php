<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Enums\OtpAudience;
use App\Exceptions\ApiException;
use App\Models\OtpCode;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Émission et vérification des codes de connexion à usage unique.
 *
 * Code à 6 chiffres, valable 5 min, 5 essais, haché en base, renvoi limité (config eventtrans.otp).
 */
class OtpService
{
    public function __construct(private readonly SmsSender $sms) {}

    public function send(PhoneNumber $phone, OtpAudience $audience, ?string $ipAddress = null): void
    {
        $latest = $this->latest($phone, $audience);
        $cooldown = (int) config('eventtrans.otp.resend_cooldown_seconds');

        if ($latest !== null && $latest->consumed_at === null && $latest->created_at->diffInSeconds(now()) < $cooldown) {
            throw new ApiException(
                'Un code vient d\'être envoyé. Patientez avant d\'en demander un nouveau.',
                'OTP_RESEND_TOO_SOON',
                429,
            );
        }

        $code = $this->generateCode();

        DB::transaction(function () use ($phone, $audience, $code, $ipAddress): void {
            OtpCode::query()
                ->where('phone', $phone->e164)
                ->where('audience', $audience)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            OtpCode::query()->create([
                'phone' => $phone->e164,
                'audience' => $audience,
                'code_hash' => $this->hash($code),
                'expires_at' => now()->addMinutes((int) config('eventtrans.otp.ttl_minutes')),
                'ip_address' => $ipAddress,
            ]);
        });

        $this->sms->send($phone, sprintf(
            '%s : votre code de connexion est %s. Il expire dans %d minutes. Ne le partagez avec personne.',
            config('eventtrans.sms.sender_name'),
            $code,
            (int) config('eventtrans.otp.ttl_minutes'),
        ));
    }

    /**
     * Vérifie le code et le consomme. Lève une ApiException avec un code d'erreur stable sinon.
     */
    public function verify(PhoneNumber $phone, OtpAudience $audience, string $code): void
    {
        // L'échec est levé hors de la transaction pour que l'incrément du compteur d'essais soit conservé.
        $isValid = DB::transaction(function () use ($phone, $audience, $code): bool {
            $otp = OtpCode::query()
                ->where('phone', $phone->e164)
                ->where('audience', $audience)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($otp === null) {
                throw new ApiException('Aucun code en cours pour ce numéro. Demandez un nouveau code.', 'OTP_NOT_FOUND');
            }

            if ($otp->isExpired()) {
                throw new ApiException('Ce code a expiré. Demandez un nouveau code.', 'OTP_EXPIRED');
            }

            if ($otp->attempts >= (int) config('eventtrans.otp.max_attempts')) {
                throw new ApiException('Trop d\'essais. Demandez un nouveau code.', 'OTP_TOO_MANY_ATTEMPTS', 429);
            }

            if (! hash_equals($otp->code_hash, $this->hash($code))) {
                $otp->increment('attempts');

                return false;
            }

            $otp->update(['consumed_at' => now()]);

            return true;
        });

        if (! $isValid) {
            throw new ApiException('Code incorrect.', 'OTP_INVALID');
        }
    }

    private function latest(PhoneNumber $phone, OtpAudience $audience): ?OtpCode
    {
        return OtpCode::query()
            ->where('phone', $phone->e164)
            ->where('audience', $audience)
            ->latest('id')
            ->first();
    }

    private function generateCode(): string
    {
        $length = (int) config('eventtrans.otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
