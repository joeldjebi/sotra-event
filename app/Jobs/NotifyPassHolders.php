<?php

namespace App\Jobs;

use App\Models\Pass;
use App\Services\Sales\PassNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Notification de masse d'un lot de détenteurs de Pass (AD-13) : SMS, WhatsApp et push.
 */
class NotifyPassHolders implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $passIds
     */
    public function __construct(
        public array $passIds,
        public string $title,
        public string $body,
        public string $template,
    ) {}

    public function handle(PassNotifier $notifier): void
    {
        $notifier->disruption(Pass::query()->with('holder')->whereIn('id', $this->passIds)->get(), $this->title, $this->body, $this->template);
    }
}
