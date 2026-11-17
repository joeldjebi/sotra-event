<?php

namespace App\Livewire\Site;

use App\Actions\Customers\DeleteCustomerAccount;
use App\Enums\OtpAudience;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Rules\ValidPhoneNumber;
use App\Services\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Page publique « Supprimer mon compte » (lien demandé par Google Play et l'App Store) : le spectateur prouve qu'il
 * détient le numéro avec un code SMS, confirme, et son compte est supprimé immédiatement.
 */
#[Layout('components.layouts.site')]
#[Title('Supprimer mon compte')]
class DeleteAccount extends Component
{
    public string $phone = '';

    public string $code = '';

    /** phone → code → confirm → done */
    public string $step = 'phone';

    public bool $understood = false;

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

        $this->step = 'code';
    }

    public function verify(OtpService $otp): void
    {
        $this->validate(['code' => ['required', 'digits:'.config('eventtrans.otp.length')]], attributes: ['code' => 'code']);

        try {
            $otp->verify(PhoneNumber::parse($this->phone), OtpAudience::Customer, $this->code);
        } catch (ApiException $exception) {
            $this->addError('code', $exception->getMessage());

            return;
        }

        if ($this->customer() === null) {
            $this->step = 'done';

            return;
        }

        $this->step = 'confirm';
    }

    public function delete(DeleteCustomerAccount $deletion): void
    {
        abort_unless($this->step === 'confirm', 403);
        $this->validate(['understood' => ['accepted']], ['understood.accepted' => 'Cochez la case pour confirmer.']);
        $customer = $this->customer();

        if ($customer !== null) {
            try {
                $deletion->handle($customer, 'web');
            } catch (ApiException $exception) {
                $this->addError('understood', $exception->getMessage());

                return;
            }

            if (Auth::guard('customer')->id() === $customer->id) {
                Auth::guard('customer')->logout();
            }
        }

        $this->step = 'done';
    }

    private function customer(): ?Customer
    {
        return Customer::query()->where('phone', PhoneNumber::parse($this->phone)->e164)->whereNull('anonymized_at')->first();
    }

    public function render(): View
    {
        return view('livewire.site.delete-account');
    }
}
