<?php

use App\Modules\Tickets\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tickets.{ticketId}', function ($user, $ticketId) {
    $ticket = Ticket::query()->find($ticketId);

    return $ticket && $user->can('view', $ticket);
});
