<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PromoterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Promoteur d'événements.
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property bool $is_active
 */
#[Fillable(['name', 'email', 'phone', 'address', 'is_active'])]
class Promoter extends Model
{
    /** @use HasFactory<PromoterFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
