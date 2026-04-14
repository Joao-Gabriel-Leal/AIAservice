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
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isRequester()) {
            return $ticket->requester_id === $user->id;
        }

        return $ticket->sector_id === $user->sector_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isRequester()) {
            return false;
        }

        return $ticket->sector_id === $user->sector_id;
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function manageBoard(User $user, Ticket $ticket): bool
    {
        return $user->isSuperAdmin() || ($user->isSectorAdmin() && $user->sector_id === $ticket->sector_id);
    }
}
