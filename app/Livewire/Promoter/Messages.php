<?php

namespace App\Livewire\Promoter;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\ComposesPromoterMessages;
use App\Livewire\Concerns\SelectsEvent;
use App\Models\PromoterMessage;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace promoteur · Messages aux spectateurs (PR-04, PR-08) : envoi et historique (destinataires, statut).
 */
class Messages extends Component
{
    use AuthorizesPermission, ComposesPromoterMessages, SelectsEvent;

    protected function permission(): Permission
    {
        return Permission::EventTracking;
    }

    public function render(): View
    {
        $event = $this->selectedEvent();

        return view('livewire.promoter.messages', [
            'event' => $event,
            'eventOptions' => $this->eventOptions(),
            'composer' => $event ? $this->composerData($event) : null,
            'messages' => $event === null ? collect() : PromoterMessage::query()->with('author')->where('event_id', $event->id)->latest('id')->get(),
        ]);
    }
}
