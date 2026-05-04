<?php

namespace App\Modules\Tickets\Policies;

use App\Models\User;
use App\Modules\Tickets\Models\TicketTimeEntry;

class TicketTimeEntryPolicy
{
    public function view(User $user, TicketTimeEntry $timeEntry): bool
    {
        return $user->hasOperationalAccess($timeEntry->ticket->sector_id);
    }

    public function update(User $user, TicketTimeEntry $timeEntry): bool
    {
        if (! $user->hasOperationalAccess($timeEntry->ticket->sector_id)) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isSectorAdmin($timeEntry->ticket->sector_id)) {
            return true;
        }

        return $timeEntry->user_id === $user->id;
    }

    public function delete(User $user, TicketTimeEntry $timeEntry): bool
    {
        return $this->update($user, $timeEntry);
    }

    public function review(User $user, TicketTimeEntry $timeEntry): bool
    {
        if (! $user->hasOperationalAccess($timeEntry->ticket->sector_id)) {
            return false;
        }

        return $user->isSuperAdmin() || $user->isSectorAdmin($timeEntry->ticket->sector_id);
    }
}
