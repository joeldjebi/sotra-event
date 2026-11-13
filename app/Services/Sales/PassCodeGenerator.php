<?php

namespace App\Services\Sales;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Code lisible du Pass (RG-02) : ET-AAMM-NNNN-COM.
 * AAMM = année et mois de l'événement ; NNNN = séquence par événement et par commune ; COM = code commune.
 */
class PassCodeGenerator
{
    public function next(Event $event, string $communeCode): string
    {
        $number = DB::transaction(function () use ($event, $communeCode): int {
            DB::table('pass_code_counters')->insertOrIgnore([
                'event_id' => $event->id,
                'commune_code' => $communeCode,
                'last_number' => 0,
            ]);

            $counter = DB::table('pass_code_counters')
                ->where('event_id', $event->id)
                ->where('commune_code', $communeCode)
                ->lockForUpdate()
                ->first();

            $next = (int) $counter->last_number + 1;

            if ($next > 9999) {
                throw new RuntimeException("Plus de 9 999 Pass pour {$communeCode} sur cet événement.");
            }

            DB::table('pass_code_counters')
                ->where('event_id', $event->id)
                ->where('commune_code', $communeCode)
                ->update(['last_number' => $next]);

            return $next;
        });

        return sprintf('ET-%s-%04d-%s', $event->starts_at->format('ym'), $number, strtoupper($communeCode));
    }
}
