<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lieu d'événement (stade, salle, esplanade).
 *
 * @property int $id
 * @property string $name
 * @property string|null $address
 * @property string $city
 * @property Point $location
 */
#[Fillable(['name', 'address', 'city', 'location'])]
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'location' => Point::class,
        ];
    }
}
