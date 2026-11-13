<?php

namespace App\Data;

/**
 * Ce que le spectateur doit faire après le lancement d'un paiement (SP-12).
 */
final readonly class PaymentInstructions
{
    public function __construct(
        public string $message,
        public ?string $redirectUrl = null,
        public ?string $simulatorUrl = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'redirect_url' => $this->redirectUrl,
            'simulator_url' => $this->simulatorUrl,
        ];
    }
}
