<?php

namespace App\Livewire\Forms;

use App\Enums\PayoutMethod;
use App\Models\Company;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CompanyForm extends Form
{
    public ?Company $company = null;

    public string $name = '';

    public string $legal_name = '';

    public string $contract_reference = '';

    public string $contract_starts_on = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $default_commission_rate = '10';

    public string $payout_method = '';

    public string $bank_name = '';

    public string $account_holder = '';

    public string $iban = '';

    public string $mobile_money_operator = '';

    public string $mobile_money_number = '';

    public bool $is_active = true;

    public function fillFrom(Company $company): void
    {
        $this->company = $company;
        $details = $company->payout_details ?? [];

        $this->fill([
            'name' => $company->name,
            'legal_name' => (string) $company->legal_name,
            'contract_reference' => (string) $company->contract_reference,
            'contract_starts_on' => (string) $company->contract_starts_on?->toDateString(),
            'email' => (string) $company->email,
            'phone' => (string) $company->phone,
            'address' => (string) $company->address,
            'default_commission_rate' => (string) $company->default_commission_rate,
            'payout_method' => (string) $company->payout_method?->value,
            'bank_name' => $details['bank_name'] ?? '',
            'account_holder' => $details['account_holder'] ?? '',
            'iban' => $details['iban'] ?? '',
            'mobile_money_operator' => $details['operator'] ?? '',
            'mobile_money_number' => $details['number'] ?? '',
            'is_active' => $company->is_active,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $bank = $this->payout_method === PayoutMethod::BankTransfer->value;
        $mobile = $this->payout_method === PayoutMethod::MobileMoney->value;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('companies', 'name')->ignore($this->company)],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'contract_reference' => ['nullable', 'string', 'max:60'],
            'contract_starts_on' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'default_commission_rate' => ['required', 'numeric', 'between:0,100'],
            'payout_method' => ['nullable', Rule::enum(PayoutMethod::class)],
            'bank_name' => [Rule::requiredIf($bank), 'nullable', 'string', 'max:120'],
            'account_holder' => [Rule::requiredIf($bank), 'nullable', 'string', 'max:120'],
            'iban' => [Rule::requiredIf($bank), 'nullable', 'string', 'max:40'],
            'mobile_money_operator' => [Rule::requiredIf($mobile), 'nullable', 'string', 'max:40'],
            'mobile_money_number' => [Rule::requiredIf($mobile), 'nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nom',
            'default_commission_rate' => 'commission par défaut',
            'bank_name' => 'banque',
            'account_holder' => 'titulaire',
            'iban' => 'IBAN / RIB',
            'mobile_money_operator' => 'opérateur',
            'mobile_money_number' => 'numéro marchand',
        ];
    }

    public function save(): Company
    {
        $this->validate();

        $payoutDetails = match ($this->payout_method) {
            PayoutMethod::BankTransfer->value => [
                'bank_name' => $this->bank_name,
                'account_holder' => $this->account_holder,
                'iban' => Str::upper(str_replace(' ', '', $this->iban)),
            ],
            PayoutMethod::MobileMoney->value => [
                'operator' => $this->mobile_money_operator,
                'number' => $this->mobile_money_number,
            ],
            default => null,
        };

        $attributes = [
            'name' => $this->name,
            'legal_name' => $this->legal_name ?: null,
            'contract_reference' => $this->contract_reference ?: null,
            'contract_starts_on' => $this->contract_starts_on ?: null,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'address' => $this->address ?: null,
            'default_commission_rate' => $this->default_commission_rate,
            'payout_method' => $this->payout_method ?: null,
            'payout_details' => $payoutDetails,
            'is_active' => $this->is_active,
        ];

        if ($this->company === null) {
            return Company::query()->create($attributes + ['slug' => $this->uniqueSlug()]);
        }

        $this->company->update($attributes);

        return $this->company;
    }

    private function uniqueSlug(): string
    {
        $base = Str::slug($this->name) ?: 'compagnie';
        $slug = $base;
        $suffix = 2;

        while (Company::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
