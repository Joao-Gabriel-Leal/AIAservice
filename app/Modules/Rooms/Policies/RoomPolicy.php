<?php

namespace App\Modules\Rooms\Policies;

use App\Models\User;
use App\Modules\Rooms\Models\Room;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageRooms();
    }

    public function view(User $user, Room $room): bool
    {
        return $user->canManageRooms();
    }

    public function create(User $user): bool
    {
        return $user->canManageRooms();
    }

    public function update(User $user, Room $room): bool
    {
        return $user->canManageRooms();
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->canManageRooms();
    }
}
