<?php

namespace App\Livewire\Site;

use App\Actions\Promoters\AttachPromoterDocument;
use App\Actions\Promoters\RegisterPromoter;
use App\Enums\OtpAudience;
use App\Enums\PromoterStatus;
use App\Exceptions\ApiException;
use App\Models\Promoter;
use App\Rules\ValidPhoneNumber;
use App\Services\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Inscription en ligne d'un promoteur : la structure, le compte du responsable et les justificatifs
 * (facultatifs : sans eux, le dossier est marqué incomplet), puis vérification du téléphone par code SMS.
 * La demande part ensuite en validation chez ACCES EVENT.
 */
#[Layout('components.layouts.site')]
#[Title('Devenir promoteur')]
class BecomePromoter extends Component
{
    use WithFileUploads;

    /** form → code → done */
    public string $step = 'form';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $rccm_number = '';

    public ?TemporaryUploadedFile $rccm_document = null;

    public ?TemporaryUploadedFile $identity_document = null;

    public string $account_name = '';

    public string $account_email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $accept_terms = false;

    public string $code = '';

    public function sendCode(OtpService $otp): void
    {
        $this->validateApplication();
        $phone = PhoneNumber::parse($this->phone);

        if (RateLimiter::tooManyAttempts('promoter-otp:'.$phone->e164, 3)) {
            $this->addError('phone', 'Trop de demandes. Réessayez dans quelques minutes.');

            return;
        }

        RateLimiter::hit('promoter-otp:'.$phone->e164, 600);

        try {
            $otp->send($phone, OtpAudience::PromoterApplicant, request()->ip());
        } catch (ApiException $exception) {
            if ($exception->errorCode !== 'OTP_RESEND_TOO_SOON') {
                $this->addError('phone', $exception->getMessage());

                return;
            }
        }

        $this->step = 'code';
    }

    public function verify(OtpService $otp, RegisterPromoter $register): void
    {
        $this->validate(['code' => ['required', 'digits:'.config('eventtrans.otp.length')]], attributes: ['code' => 'code']);
        $phone = PhoneNumber::parse($this->phone);

        try {
            $otp->verify($phone, OtpAudience::PromoterApplicant, $this->code);
        } catch (ApiException $exception) {
            $this->addError('code', $exception->getMessage());

            return;
        }

        // Les données sont revérifiées : un autre dossier a pu prendre le nom ou l'e-mail entre-temps.
        $this->validateApplication();

        $register->handle([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $phone->e164,
            'address' => $this->address,
            'rccm_number' => $this->rccm_number,
            'account_name' => $this->account_name,
            'account_email' => $this->account_email,
            'password' => $this->password,
        ], [
            'rccm' => $this->rccm_document,
            'identity' => $this->identity_document,
        ]);

        $this->reset(['password', 'password_confirmation', 'code', 'rccm_document', 'identity_document']);
        $this->step = 'done';
    }

    public function back(): void
    {
        $this->reset('code');
        $this->step = 'form';
    }

    private function validateApplication(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('promoters', 'name')],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['required', 'string', new ValidPhoneNumber, function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && PhoneNumber::isValid($value) && Promoter::query()
                    ->where('phone', PhoneNumber::parse($value)->e164)
                    ->whereIn('status', [PromoterStatus::Pending, PromoterStatus::Approved, PromoterStatus::Suspended])
                    ->exists()) {
                    $fail('Ce numéro est déjà utilisé par un promoteur. Connectez-vous à votre espace ou contactez ACCES EVENT.');
                }
            }],
            'address' => ['nullable', 'string', 'max:255'],
            'rccm_number' => ['nullable', 'string', 'max:60'],
            'rccm_document' => AttachPromoterDocument::RULES,
            'identity_document' => AttachPromoterDocument::RULES,
            'account_name' => ['required', 'string', 'max:120'],
            'account_email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', PasswordRule::default()],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Acceptez les conditions et la politique de confidentialité pour continuer.',
        ], [
            'name' => 'nom de la structure',
            'phone' => 'téléphone',
            'address' => 'adresse',
            'rccm_number' => 'numéro RCCM',
            'rccm_document' => 'extrait RCCM',
            'identity_document' => 'pièce d\'identité',
            'account_name' => 'nom du responsable',
            'account_email' => 'e-mail de connexion',
            'password' => 'mot de passe',
        ]);
    }

    public function render(): View
    {
        return view('livewire.site.become-promoter');
    }
}
