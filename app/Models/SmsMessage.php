<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SMS émis par la plateforme (journal et boîte d'envoi de développement).
 *
 * @property int $id
 * @property string $to
 * @property string $body
 * @property string $driver
 * @property string $status
 */
class SmsMessage extends Model
{
    protected $guarded = ['id'];
}
