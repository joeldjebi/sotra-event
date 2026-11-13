<?php

namespace App\Actions\Passes;

use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Pass;
use App\Services\Sales\PassNotifier;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * « Offrir ce Pass » (SP-19, RG-04) : transfert à un autre numéro tant que l'aller n'est pas scanné.
 * La version du jeton est incrémentée : l'ancien QR (et l'ancien lien) deviennent invalides.
 */
class TransferPass
{
    public function __construct(private readonly PassNotifier $notifier) {}

    public function handle(Pass $pass, Customer $from, string $phone): Pass
    {
        if ($pass->holder_id !== $from->id) {
            throw new ApiException('Ce Pass ne vous appartient pas.', 'PASS_NOT_OWNED', 403);
        }

        if (! $pass->isTransferable()) {
            throw new ApiException('Ce Pass ne peut plus être offert.', 'PASS_NOT_TRANSFERABLE');
        }

        $to = PhoneNumber::parse($phone);

        if ($to->e164 === $from->phone) {
            throw new ApiException('Ce Pass est déjà à vous.', 'PASS_SAME_HOLDER');
        }

        $pass = DB::transaction(function () use ($pass, $from, $to): Pass {
            $pass = Pass::query()->lockForUpdate()->findOrFail($pass->id);

            if (! $pass->isTransferable()) {
                throw new ApiException('Ce Pass ne peut plus être offert.', 'PASS_NOT_TRANSFERABLE');
            }

            $recipient = Customer::query()->firstOrCreate(['phone' => $to->e164]);

            $pass->transfers()->create([
                'from_customer_id' => $from->id,
                'to_customer_id' => $recipient->id,
                'previous_token_version' => $pass->token_version,
            ]);

            $pass->update(['holder_id' => $recipient->id, 'token_version' => $pass->token_version + 1]);

            AuditLog::record('pass_transferred', $pass, ['holder_id' => $from->id], ['holder_id' => $recipient->id]);

            return $pass;
        });

        $this->notifier->passTransferred($pass->fresh(['holder', 'event', 'boardingPoint']), $from);

        return $pass;
    }
}
