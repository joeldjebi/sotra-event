<?php

namespace App\Data;

/**
 * Réponse de l'agrégateur à une demande de remboursement (PA-04).
 * « unsupported » : l'opérateur ne permet pas le remboursement par API, il passe en traitement manuel.
 */
final readonly class RefundResult
{
    public const string REFUNDED = 'refunded';

    public const string UNSUPPORTED = 'unsupported';

    public const string FAILED = 'failed';

    public function __construct(
        public string $status,
        public ?string $reference = null,
        public ?string $message = null,
    ) {}

    public static function refunded(string $reference): self
    {
        return new self(self::REFUNDED, $reference);
    }

    public static function unsupported(string $message): self
    {
        return new self(self::UNSUPPORTED, null, $message);
    }

    public static function failed(string $message): self
    {
        return new self(self::FAILED, null, $message);
    }
}
