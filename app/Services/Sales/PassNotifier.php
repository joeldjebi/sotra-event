<?php

namespace App\Services\Sales;

use App\Contracts\PushSender;
use App\Contracts\SmsSender;
use App\Contracts\WhatsAppSender;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pass;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\URL;

/**
 * Envoi des Pass au spectateur : application (push), SMS et WhatsApp (SP-12, WA-04).
 * Le lien envoyé est signé et contient la version du Pass : il cesse de fonctionner après un transfert.
 */
class PassNotifier
{
    public function __construct(
        private readonly SmsSender $sms,
        private readonly WhatsAppSender $whatsApp,
        private readonly PushSender $push,
    ) {}

    public static function publicUrl(Pass $pass): string
    {
        return URL::signedRoute('site.passes.public', ['pass' => $pass->code, 'v' => $pass->token_version]);
    }

    public function orderPaid(Order $order): void
    {
        foreach ($order->passes->groupBy('holder_id') as $passes) {
            /** @var Pass $first */
            $first = $passes->first();
            $this->sendPasses($first->holder, $passes->all(), $order->customer->is($first->holder) ? null : $order->customer);
        }

        if (! $order->passes->contains(fn (Pass $pass) => $pass->holder_id === $order->customer_id)) {
            $this->sms->send(PhoneNumber::parse($order->customer->phone), sprintf(
                'EVENT TRANS : votre cadeau (%d Pass pour %s) a bien été envoyé au %s.',
                $order->quantity,
                $order->event->name,
                $order->beneficiary_phone,
            ));
        }
    }

    public function passTransferred(Pass $pass, Customer $from): void
    {
        $this->sendPasses($pass->holder, [$pass], $from);
        $this->sms->send(PhoneNumber::parse($from->phone), "EVENT TRANS : le Pass {$pass->code} a bien été offert. Il n'est plus valable sur votre téléphone.");
    }

    /**
     * @param  list<Pass>  $passes
     */
    private function sendPasses(Customer $holder, array $passes, ?Customer $offeredBy): void
    {
        $phone = PhoneNumber::parse($holder->phone);
        $first = $passes[0];
        $event = $first->event;
        $point = $first->boardingPoint;
        $intro = $offeredBy !== null ? "Le {$offeredBy->phone} vous offre " : 'Votre ';

        foreach ($passes as $pass) {
            $body = sprintf(
                'EVENT TRANS : %sPass %s %s pour %s (%s). Montée : %s · %s à %s. Code %s. Votre Pass : %s',
                $intro,
                $pass->offer->label(),
                mb_strtolower($pass->pass_type->label()),
                $event->name,
                $event->starts_at->translatedFormat('j M'),
                $point->commune,
                $point->place,
                $point->boardingCallAt()->format('H\hi'),
                $pass->code,
                self::publicUrl($pass),
            );

            $this->sms->send($phone, $body);
            $this->whatsApp->send($phone, 'pass_issued', $body, ['code' => $pass->code, 'url' => self::publicUrl($pass)]);
        }

        $this->push->send($holder, 'Votre Pass est prêt', count($passes).' Pass pour '.$event->name, ['codes' => array_map(fn (Pass $pass) => $pass->code, $passes)]);
    }
}
