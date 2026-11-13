<?php

namespace App\Livewire\Site;

use App\Actions\Passes\TransferPass;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Pass;
use App\Rules\ValidPhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Mon Pass (SP-14 à SP-19) : QR signé, montée, retour, « Offrir ce Pass ».
 */
#[Layout('components.layouts.site')]
#[Title('Mon Pass')]
class PassShow extends Component
{
    public Pass $pass;

    public bool $showGift = false;

    public string $phone = '';

    public function mount(Pass $pass): void
    {
        $customer = auth('customer')->user();
        abort_unless($customer instanceof Customer && $pass->holder_id === $customer->id, 404);
        $this->pass = $pass;
    }

    public function offer(TransferPass $transfer): Redirector|RedirectResponse|null
    {
        $this->validate(['phone' => ['required', 'string', new ValidPhoneNumber]], attributes: ['phone' => 'numéro']);

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        try {
            $transfer->handle($this->pass, $customer, $this->phone);
        } catch (ApiException $exception) {
            $this->addError('phone', $exception->getMessage());

            return null;
        }

        session()->flash('flash', "Pass {$this->pass->code} offert : il a été envoyé au bénéficiaire par SMS et WhatsApp.");

        return $this->redirectRoute('site.passes.index');
    }

    public function render(): View
    {
        return view('livewire.site.pass-show', [
            'pass' => $this->pass->load(['event.venue', 'event.dropZones', 'boardingPoint']),
        ]);
    }
}
