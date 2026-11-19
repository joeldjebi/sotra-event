<?php

namespace App\Models;

use App\Enums\PromoterStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\PromoterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Promoteur d'événements : une organisation, avec son équipe et ses demandes d'événement.
 *
 * Il s'inscrit en ligne (validation par ACCES EVENT) ou il est créé dans la console (validé d'office).
 * Le RCCM et les justificatifs sont facultatifs : sans eux, le dossier est marqué incomplet.
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property bool $is_active
 * @property PromoterStatus $status
 * @property string|null $rccm_number
 * @property string|null $rccm_document_path
 * @property string|null $id_document_path
 * @property Carbon|null $applied_at
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property string|null $review_note
 * @property-read User|null $reviewer
 */
#[Fillable([
    'name', 'email', 'phone', 'address', 'is_active', 'status', 'rccm_number', 'rccm_document_path', 'id_document_path',
    'applied_at', 'phone_verified_at', 'reviewed_at', 'reviewed_by', 'review_note',
])]
class Promoter extends Model
{
    /** @use HasFactory<PromoterFactory> */
    use Auditable, HasFactory;

    /** Justificatifs du dossier, avec leur libellé. */
    public const DOCUMENTS = [
        'rccm' => ['column' => 'rccm_document_path', 'label' => 'Extrait RCCM'],
        'identity' => ['column' => 'id_document_path', 'label' => 'Pièce d\'identité du responsable'],
    ];

    protected $attributes = [
        'status' => 'valide',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status' => PromoterStatus::class,
            'applied_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Le statut fait foi ; is_active le reflète pour les listes qui ne proposent que les promoteurs actifs.
     */
    protected static function booted(): void
    {
        static::saving(function (Promoter $promoter): void {
            $promoter->is_active = $promoter->status === PromoterStatus::Approved;
        });
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<EventRequest, $this>
     */
    public function eventRequests(): HasMany
    {
        return $this->hasMany(EventRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === PromoterStatus::Approved;
    }

    /**
     * Éléments du dossier non fournis (numéro RCCM et justificatifs).
     *
     * @return list<string>
     */
    public function missingItems(): array
    {
        $missing = blank($this->rccm_number) ? ['Numéro RCCM'] : [];

        foreach (self::DOCUMENTS as $document) {
            if (blank($this->{$document['column']})) {
                $missing[] = $document['label'];
            }
        }

        return $missing;
    }

    public function isFileComplete(): bool
    {
        return $this->missingItems() === [];
    }
}
