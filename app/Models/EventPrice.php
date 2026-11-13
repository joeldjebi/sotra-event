<?php

namespace App\Models;

use App\Enums\Offer;
use App\Enums\PassType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Prix d'un Pass pour une zone, une offre et un type (FCFA hors frais).
 * Les changements de grille sont journalisés en bloc (« pricing_updated ») plutôt que ligne par ligne.
 *
 * @property int $id
 * @property int $event_id
 * @property int $zone
 * @property Offer $offer
 * @property PassType $pass_type
 * @property int $price
 */
#[Fillable(['event_id', 'zone', 'offer', 'pass_type', 'price'])]
class EventPrice extends Model
{
    protected function casts(): array
    {
        return [
            'zone' => 'integer',
            'offer' => Offer::class,
            'pass_type' => PassType::class,
            'price' => 'integer',
        ];
    }
}
