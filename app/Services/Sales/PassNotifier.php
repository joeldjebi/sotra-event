<?php

namespace App\Services\Sales;

use App\Contracts\PushSender;
use App\Contracts\SmsSender;
use App\Contracts\WhatsAppSender;
use App\Models\Bus;
use App\Models\Customer;
use App\Models\Mission;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Refund;
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
     * Remboursement effectué (SP-21) : montant, retenue éventuelle et moyen de paiement d'origine.
     */
    public function refunded(Refund $refund): void
    {
        $retained = $refund->retained_amount > 0 ? ' ('.number_format($refund->retained_amount, 0, ',', ' ').' FCFA de frais d\'annulation retenus)' : '';
        $body = sprintf(
            'EVENT TRANS : le Pass %s est remboursé : %s FCFA sur votre %s%s. Réf. %s.',
            $refund->pass->code,
            number_format($refund->amount, 0, ',', ' '),
            $refund->payment->method->label(),
            $retained,
            $refund->reference,
        );
        $phone = PhoneNumber::parse($refund->customer->phone);
        $this->sms->send($phone, $body);
        $this->push->send($refund->customer, 'Remboursement effectué', $body, ['code' => $refund->pass->code]);
    }

    /**
     * Message de masse aux détenteurs (AD-13) : annulation ou report, un message par spectateur.
     *
     * @param  iterable<Pass>  $passes
     */
    public function disruption(iterable $passes, string $title, string $body, string $template): void
    {
        foreach (collect($passes)->unique('holder_id') as $pass) {
            $text = "EVENT TRANS : {$body}";
            $phone = PhoneNumber::parse($pass->holder->phone);
            $this->sms->send($phone, $text);
            $this->whatsApp->send($phone, $template, $text, ['event' => $pass->event_id]);
            $this->push->send($pass->holder, $title, $body, ['event_id' => $pass->event_id]);
        }
    }

    /**
     * Bus remplacé après une panne (RG-20) : les passagers sont prévenus, leur Pass reste valable.
     *
     * @param  list<Pass>  $passes
     */
    public function busReplaced(array $passes, Bus $bus): void
    {
        foreach ($passes as $pass) {
            $body = "EVENT TRANS : votre bus a eu une panne. Le bus {$bus->number} vous prend en charge, votre Pass {$pass->code} reste valable. Merci de votre patience.";
            $phone = PhoneNumber::parse($pass->holder->phone);
            $this->sms->send($phone, $body);
            $this->whatsApp->send($phone, 'bus_replaced', $body, ['code' => $pass->code, 'bus' => $bus->number]);
            $this->push->send($pass->holder, 'Changement de bus', $body, ['code' => $pass->code]);
        }
    }

    /**
     * Retard important au départ (SP-21) : les passagers sont prévenus qu'une réclamation collective est ouverte.
     *
     * @param  list<Pass>  $passes
     */
    public function busDelayed(array $passes, Mission $mission, int $minutes): void
    {
        foreach (collect($passes)->unique('holder_id') as $pass) {
            $body = "EVENT TRANS : votre bus ({$mission->boardingPoint->commune}, {$mission->direction->label()}) a {$minutes} min de retard. Nous nous en excusons : une réclamation collective est ouverte, inutile d'en déposer une.";
            $phone = PhoneNumber::parse($pass->holder->phone);
            $this->sms->send($phone, $body);
            $this->push->send($pass->holder, 'Retard de votre bus', $body, ['code' => $pass->code]);
        }
    }

    /**
     * Rappel avant le départ (SP-21) : la veille (J-1) et deux heures avant la convocation (H-2).
     */
    public function reminder(Pass $pass, string $kind): void
    {
        $point = $pass->boardingPoint;
        $when = $kind === 'h2' ? "aujourd'hui à" : 'demain à';
        $body = sprintf(
            'EVENT TRANS : rappel, %s. Montée %s %s · %s. Présentez votre Pass %s au convoyeur. %s',
            $pass->event->name,
            $when,
            $point->boardingCallAt()->format('H\hi'),
            $point->commune.' · '.$point->place,
            $pass->code,
            self::publicUrl($pass),
        );
        $phone = PhoneNumber::parse($pass->holder->phone);

        $this->sms->send($phone, $body);
        $this->whatsApp->send($phone, 'pass_reminder_'.$kind, $body, ['code' => $pass->code]);
        $this->push->send($pass->holder, $kind === 'h2' ? 'Votre bus part bientôt' : 'C\'est demain', $body, ['code' => $pass->code]);
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
