<?php

namespace App\Livewire\Site;

use App\Enums\OtpAudience;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Rules\ValidPhoneNumber;
use App\Services\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Connexion du spectateur sur le site : téléphone puis code reçu par SMS (§ 2 CDC).
 * Le compte est créé à la première connexion.
 */
#[Layout('components.layouts.site')]
#[Title('Connexion')]
class Login extends Component
{
    public string $phone = '';

    public string $code = '';

    public bool $codeSent = false;

    public function sendCode(OtpService $otp): void
    {
        $this->validate(['phone' => ['required', 'string', new ValidPhoneNumber]], attributes: ['phone' => 'téléphone']);
        $phone = PhoneNumber::parse($this->phone);

        if (RateLimiter::tooManyAttempts('site-otp:'.$phone->e164, 3)) {
            $this->addError('phone', 'Trop de demandes. Réessayez dans quelques minutes.');

            return;
        }

        RateLimiter::hit('site-otp:'.$phone->e164, 600);

        try {
            $otp->send($phone, OtpAudience::Customer, request()->ip());
        } catch (ApiException $exception) {
            if ($exception->errorCode !== 'OTP_RESEND_TOO_SOON') {
                $this->addError('phone', $exception->getMessage());

                return;
            }
        }

        $this->codeSent = true;
    }

    public function verify(OtpService $otp): Redirector|RedirectResponse|null
    {
        $this->validate(['code' => ['required', 'digits:'.config('eventtrans.otp.length')]], attributes: ['code' => 'code']);
        $phone = PhoneNumber::parse($this->phone);

        try {
            $otp->verify($phone, OtpAudience::Customer, $this->code);
        } catch (ApiException $exception) {
            $this->addError('code', $exception->getMessage());

            return null;
        }

        $customer = Customer::query()->firstOrCreate(['phone' => $phone->e164]);
        $customer->forceFill(['last_login_at' => now()])->save();

        Auth::guard('customer')->login($customer, remember: true);
        session()->regenerate();

        return $this->redirectIntended(route('site.passes.index'));
    }

    public function render(): View
    {
        return view('livewire.site.login');
    }
}
