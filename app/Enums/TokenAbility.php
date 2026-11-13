<?php

namespace App\Enums;

/**
 * Capacités des jetons API (Sanctum) délivrés aux applications mobiles.
 */
enum TokenAbility: string
{
    case Customer = 'customer';
    case Conveyor = 'conveyor';
    case Tech = 'tech';
}
