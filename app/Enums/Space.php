<?php

namespace App\Enums;

/**
 * Espaces web de la plateforme. Chaque rôle backoffice appartient à un seul espace.
 */
enum Space: string
{
    case Admin = 'admin';
    case Company = 'company';
    case Promoter = 'promoter';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Console ACCES EVENT',
            self::Company => 'Espace compagnie',
            self::Promoter => 'Espace promoteur',
        };
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Company => 'company.dashboard',
            self::Promoter => 'promoter.dashboard',
        };
    }
}
