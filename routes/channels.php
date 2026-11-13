<?php

use App\Enums\Space;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
| Exploitation jour J d'un événement : ACCES EVENT, la compagnie de l'événement et son promoteur.
*/
Broadcast::channel('events.{eventId}.operations', function (User $user, int $eventId): bool {
    $event = Event::query()->withoutGlobalScopes()->find($eventId);

    return $event !== null && match ($user->space()) {
        Space::Admin => true,
        Space::Company => $user->company_id === $event->company_id,
        Space::Promoter => $user->promoter_id === $event->promoter_id,
        null => false,
    };
});
