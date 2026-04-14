<?php

namespace App\Modules\Rooms\Policies;

use App\Models\User;
use App\Modules\Rooms\Models\Room;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin();
    }

    public function view(User $user, Room $room): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin($room->sector_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin();
    }

    public function update(User $user, Room $room): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin($room->sector_id);
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->isSuperAdmin() || $user->isSectorAdmin($room->sector_id);
    }
}
