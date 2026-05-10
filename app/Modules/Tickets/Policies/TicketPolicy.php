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

        if ($ticket->isSubelement()) {
            return $user->canOperateBoard($ticket->board);
        }

        return $ticket->requester_id === $user->id
            || $user->canOperateBoard($ticket->board);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->canOperateBoard($ticket->board);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->canOperateBoard($ticket->board);
    }

    public function restore(User $user, Ticket $ticket): bool
    {
        return $user->canOperateBoard($ticket->board);
    }

    public function closeOwn(User $user, Ticket $ticket): bool
    {
        return ! $ticket->isSubelement()
            && $ticket->requester_id === $user->id
            && ! $ticket->isClosed();
    }

    public function reopenOwn(User $user, Ticket $ticket): bool
    {
        return ! $ticket->isSubelement()
            && $ticket->requester_id === $user->id
            && $ticket->isClosed();
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && ! $ticket->isClosed();
    }

    public function viewInternalUpdates(User $user, Ticket $ticket): bool
    {
        return $user->canOperateBoard($ticket->board);
    }

    public function commentInternally(User $user, Ticket $ticket): bool
    {
        return $this->viewInternalUpdates($user, $ticket) && ! $ticket->isClosed();
    }

    public function rate(User $user, Ticket $ticket): bool
    {
        return $ticket->canBeRatedBy($user);
    }

    public function viewTimeTracking(User $user, Ticket $ticket): bool
    {
        return $user->canOperateBoard($ticket->board);
    }

    public function trackTime(User $user, Ticket $ticket): bool
    {
        return $this->viewTimeTracking($user, $ticket);
    }

    public function manageBoard(User $user, Ticket $ticket): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin($ticket->sector_id);
    }
}
