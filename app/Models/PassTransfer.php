<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Transfert d'un Pass à un autre numéro (RG-04).
 *
 * @property int $id
 * @property int $pass_id
 * @property int $from_customer_id
 * @property int $to_customer_id
 * @property int $previous_token_version
 */
#[Fillable(['pass_id', 'from_customer_id', 'to_customer_id', 'previous_token_version'])]
class PassTransfer extends Model
{
    public const UPDATED_AT = null;
}
