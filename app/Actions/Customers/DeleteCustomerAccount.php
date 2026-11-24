<?php

namespace App\Actions\Customers;

use App\Enums\PassStatus;
use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\OtpCode;
use App\Models\OutboundMessage;
use App\Models\Pass;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\DB;

/**
 * Suppression du compte spectateur (exigence des stores, loi ivoirienne n° 2013-450) :
 * les données personnelles sont effacées immédiatement (téléphone, nom, connexions, codes, journaux de messages),
 * le compte est anonymisé ; les commandes, paiements et remboursements restent, sans lien avec la personne,
 * pour les obligations comptables. Refusée tant que le spectateur détient un Pass pour un événement à venir.
 */
class DeleteCustomerAccount
{
    public function handle(Customer $customer, string $channel): void
    {
        $upcoming = Pass::query()
            ->where('holder_id', $customer->id)
            ->whereIn('status', [PassStatus::PendingPayment, PassStatus::Valid, PassStatus::OutboundUsed, PassStatus::ReturnUsed])
            ->whereHas('event', fn ($query) => $query->withoutGlobalScopes()->where('ends_at', '>', now()))
            ->count();

        if ($upcoming > 0) {
            throw new ApiException(
                "Vous détenez {$upcoming} Pass pour un événement à venir. Annulez-les depuis « Mes Pass » (remboursement selon les conditions de vente) ou attendez la fin de l’événement, puis supprimez votre compte.",
                'ACCOUNT_HAS_UPCOMING_PASSES',
                409,
            );
        }

        DB::transaction(function () use ($customer, $channel) {
            $phone = $customer->phone;

            $customer->tokens()->delete();
            OtpCode::query()->where('phone', $phone)->delete();
            SmsMessage::query()->where('to', $phone)->delete();
            OutboundMessage::query()->whereIn('recipient', [$phone, 'customer:'.$customer->id])->delete();

            $customer->forceFill([
                'phone' => 'supprime-'.$customer->id,
                'password' => null,
                'first_name' => null,
                'last_name' => null,
                'email' => null,
                'remember_token' => null,
                'anonymized_at' => now(),
            ])->save();

            AuditLog::record('customer_account_deleted', $customer, [], ['channel' => $channel]);
        });
    }
}
