<?php

namespace App\Modules\Tickets\Policies;

use App\Models\User;
use App\Modules\Tickets\Models\TicketBoard;

class TicketBoardPolicy
{
    public function view(User $user, TicketBoard $board): bool
    {
        return $user->isSuperAdmin() || $user->sector_id === $board->sector_id;
    }

    public function update(User $user, TicketBoard $board): bool
    {
        return $user->isSuperAdmin() || ($user->isSectorAdmin() && $user->sector_id === $board->sector_id);
    }
}
