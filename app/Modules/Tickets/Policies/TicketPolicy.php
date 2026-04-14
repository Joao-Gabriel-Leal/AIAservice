<?php

namespace App\Modules\Tickets\Policies;

use App\Models\User;
use App\Modules\Tickets\Models\Ticket;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isSuperAdmin()
            || $ticket->requester_id === $user->id
            || $user->hasOperationalAccess($ticket->sector_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isSuperAdmin() || $user->hasOperationalAccess($ticket->sector_id);
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function rate(User $user, Ticket $ticket): bool
    {
        return $ticket->requester_id === $user->id
            && $ticket->isClosed()
            && ! $ticket->rating()->exists();
    }

    public function manageBoard(User $user, Ticket $ticket): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin($ticket->sector_id);
    }
}
