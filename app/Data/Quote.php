<?php

namespace App\Data;

/**
 * Montants d'une commande, en FCFA entiers (RG-11 à RG-16). Les montants « par Pass » sont figés sur chaque Pass.
 */
final readonly class Quote
{
    public function __construct(
        public int $quantity,
        public int $unitPrice,
        public int $unitFee,
        public int $unitCommission,
        public int $unitCompanyAmount,
        public string $feeRate,
        public string $commissionRate,
    ) {}

    public function subtotal(): int
    {
        return $this->unitPrice * $this->quantity;
    }

    public function fee(): int
    {
        return $this->unitFee * $this->quantity;
    }

    public function commission(): int
    {
        return $this->unitCommission * $this->quantity;
    }

    public function companyAmount(): int
    {
        return $this->unitCompanyAmount * $this->quantity;
    }

    public function total(): int
    {
        return $this->subtotal() + $this->fee();
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'subtotal' => $this->subtotal(),
            'fee_rate' => $this->feeRate,
            'fee_amount' => $this->fee(),
            'total' => $this->total(),
        ];
    }
}
