<?php

namespace App\Services\Quality;

use App\Enums\ScanResult;
use App\Exceptions\ApiException;
use App\Models\Pass;
use App\Models\Review;
use App\Models\Scan;

/**
 * Avis après le trajet (SP-22) : un avis par Pass, possible dès qu'un trajet a été scanné.
 * L'avis est rattaché au dernier bus emprunté, pour noter son chauffeur et son convoyeur (QU-04).
 */
class ReviewService
{
    /**
     * @param  array{punctuality: int, comfort: int, courtesy: int, driving: int, information: int}  $ratings
     */
    public function submit(Pass $pass, array $ratings, ?string $comment = null): Review
    {
        if ($pass->review()->exists()) {
            throw new ApiException('Vous avez déjà donné votre avis pour ce Pass. Merci !', 'REVIEW_ALREADY_SENT', 409);
        }

        $lastScan = $this->lastAcceptedScan($pass)
            ?? throw new ApiException('Vous pourrez donner votre avis après votre trajet.', 'REVIEW_NOT_AVAILABLE', 422);

        return Review::query()->create([
            'pass_id' => $pass->id,
            'event_id' => $pass->event_id,
            'company_id' => $pass->event->company_id,
            'mission_id' => $lastScan->mission_id,
            'customer_id' => $pass->holder_id,
            'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
        ] + $ratings);
    }

    public function canReview(Pass $pass): bool
    {
        return ! $pass->review()->exists() && $this->lastAcceptedScan($pass) !== null;
    }

    private function lastAcceptedScan(Pass $pass): ?Scan
    {
        return $pass->scans()
            ->whereIn('result', [ScanResult::Green, ScanResult::Orange])
            ->where('conflict', false)
            ->latest('scanned_at')
            ->first();
    }
}
