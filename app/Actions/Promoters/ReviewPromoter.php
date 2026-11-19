<?php

namespace App\Actions\Promoters;

use App\Contracts\SmsSender;
use App\Enums\PromoterStatus;
use App\Models\AuditLog;
use App\Models\Promoter;
use App\Support\PhoneNumber;
use Illuminate\Validation\ValidationException;

/**
 * Décisions d'ACCES EVENT sur un promoteur : valider, refuser, suspendre, réactiver.
 * Chaque décision est journalisée et annoncée au promoteur par SMS.
 */
class ReviewPromoter
{
    public function __construct(private readonly SmsSender $sms) {}

    public function approve(Promoter $promoter): void
    {
        $this->transition($promoter, [PromoterStatus::Pending, PromoterStatus::Rejected], PromoterStatus::Approved, null,
            'votre compte promoteur est validé. Connectez-vous à votre espace : '.route('login'));
    }

    public function reject(Promoter $promoter, string $reason): void
    {
        $this->transition($promoter, [PromoterStatus::Pending], PromoterStatus::Rejected, $reason,
            'votre demande de compte promoteur n\'a pas été retenue. Motif : '.$reason);
    }

    public function suspend(Promoter $promoter, string $reason): void
    {
        $this->transition($promoter, [PromoterStatus::Approved], PromoterStatus::Suspended, $reason,
            'votre compte promoteur est suspendu. Motif : '.$reason);
    }

    public function reactivate(Promoter $promoter): void
    {
        $this->transition($promoter, [PromoterStatus::Suspended], PromoterStatus::Approved, null,
            'votre compte promoteur est réactivé.');
    }

    /**
     * @param  list<PromoterStatus>  $from
     */
    private function transition(Promoter $promoter, array $from, PromoterStatus $to, ?string $note, string $message): void
    {
        if (! in_array($promoter->status, $from, true)) {
            throw ValidationException::withMessages(['status' => "Impossible : le promoteur est « {$promoter->status->label()} »."]);
        }

        $previous = $promoter->status;

        $promoter->update([
            'status' => $to,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'review_note' => $note,
        ]);

        AuditLog::record('promoter_status_changed', $promoter, ['status' => $previous->value], ['status' => $to->value, 'note' => $note]);

        if (filled($promoter->phone) && PhoneNumber::isValid($promoter->phone)) {
            $this->sms->send(PhoneNumber::parse($promoter->phone), sprintf('%s : %s, %s', config('eventtrans.sms.sender_name'), $promoter->name, $message));
        }
    }
}
