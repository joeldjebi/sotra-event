<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RefundStatus: string
{
    use HasOptions;

    case Pending = 'a_traiter';
    case Refunded = 'rembourse';
    case Failed = 'echec';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À traiter',
            self::Refunded => 'Remboursé',
            self::Failed => 'Échec',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Refunded => 'success',
            self::Failed => 'danger',
        };
    }
}
