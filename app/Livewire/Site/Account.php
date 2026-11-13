<?php

namespace App\Livewire\Site;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Profil du spectateur (SP-24) : nom, téléphone (identifiant), langue.
 */
#[Layout('components.layouts.site')]
#[Title('Profil')]
class Account extends Component
{
    public string $first_name = '';

    public string $last_name = '';

    public function mount(): void
    {
        $customer = $this->customer();
        $this->first_name = (string) $customer->first_name;
        $this->last_name = (string) $customer->last_name;
    }

    public function save(): void
    {
        $this->validate([
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
        ], attributes: ['first_name' => 'prénom', 'last_name' => 'nom']);

        $this->customer()->update(['first_name' => $this->first_name ?: null, 'last_name' => $this->last_name ?: null]);
        $this->dispatch('notify', message: 'Profil enregistré.');
    }

    public function logout(): Redirector|RedirectResponse
    {
        Auth::guard('customer')->logout();
        session()->regenerateToken();

        return $this->redirectRoute('home');
    }

    private function customer(): Customer
    {
        $customer = auth('customer')->user();
        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }

    public function render(): View
    {
        return view('livewire.site.account', ['customer' => $this->customer()]);
    }
}
