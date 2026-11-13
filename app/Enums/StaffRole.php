<?php

namespace App\Enums;

/**
 * Personnel terrain d'une compagnie. Seuls convoyeurs et techniciens se connectent (OTP).
 */
enum StaffRole: string
{
    case Driver = 'chauffeur';
    case Conveyor = 'convoyeur';
    case Technician = 'technicien';

    public function label(): string
    {
        return match ($this) {
            self::Driver => 'Chauffeur',
            self::Conveyor => 'Convoyeur',
            self::Technician => 'Technicien',
        };
    }

    public function canSignIn(): bool
    {
        return $this !== self::Driver;
    }

    /**
     * Capacité du jeton Sanctum délivré à ce profil.
     */
    public function tokenAbility(): ?TokenAbility
    {
        return match ($this) {
            self::Driver => null,
            self::Conveyor => TokenAbility::Conveyor,
            self::Technician => TokenAbility::Tech,
        };
    }
}
