<?php

namespace App\Actions\Promoters;

use App\Models\AuditLog;
use App\Models\Promoter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Enregistre un justificatif du dossier promoteur sur le disque privé (jamais accessible par une URL publique).
 */
class AttachPromoterDocument
{
    /** Règles de validation communes aux formulaires qui reçoivent un justificatif. */
    public const RULES = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];

    public function handle(Promoter $promoter, string $document, UploadedFile $file): void
    {
        $column = Promoter::DOCUMENTS[$document]['column'] ?? throw new InvalidArgumentException("Justificatif inconnu : {$document}");

        $previous = $promoter->{$column};
        $promoter->update([$column => $file->store("promoters/{$promoter->id}", 'local')]);

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        AuditLog::record('promoter_document_attached', $promoter, [], ['document' => $document]);
    }
}
