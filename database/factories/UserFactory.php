<?php

namespace Database\Factories;

use App\Enums\GlobalUserRole;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Tickets\Models\TicketBoard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function configure(): static
    {
        return $this
            ->afterMaking(function (User $user) {
                if ($user->role === UserRole::SUPER_ADMIN) {
                    $user->global_role = GlobalUserRole::SUPER_ADMIN;
                }

                if ($user->role === UserRole::DEV) {
                    $user->global_role = GlobalUserRole::DEV;
                }
            })
            ->afterCreating(function (User $user) {
                if ($user->role === UserRole::SUPER_ADMIN) {
                    if ($user->global_role !== GlobalUserRole::SUPER_ADMIN) {
                        $user->forceFill(['global_role' => GlobalUserRole::SUPER_ADMIN])->save();
                    }

                    return;
                }

                if ($user->role === UserRole::DEV) {
                    if ($user->global_role !== GlobalUserRole::DEV) {
                        $user->forceFill(['global_role' => GlobalUserRole::DEV])->save();
                    }

                    return;
                }

                if (! $user->sector_id) {
                    return;
                }

                $accessLevel = match ($user->role) {
                    UserRole::SECTOR_ADMIN => 'sector_admin',
                    UserRole::TECHNICIAN => 'technician',
                    default => 'requester',
                };

                $user->sectorAccesses()->updateOrCreate(
                    ['sector_id' => $user->sector_id],
                    ['access_level' => $accessLevel],
                );

                if ($user->role === UserRole::TECHNICIAN) {
                    TicketBoard::query()
                        ->where('sector_id', $user->sector_id)
                        ->where('is_active', true)
                        ->get()
                        ->each(fn (TicketBoard $board) => $board->operators()->syncWithoutDetaching([$user->id]));
                }
            });
    }

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'profile_photo_path' => null,
            'role' => UserRole::REQUESTER,
            'global_role' => GlobalUserRole::COLLABORATOR,
            'sector_id' => null,
            'room_id' => null,
            'must_change_password' => false,
            'is_active' => true,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::SUPER_ADMIN,
            'global_role' => GlobalUserRole::SUPER_ADMIN,
            'sector_id' => null,
            'room_id' => null,
        ]);
    }

    public function developer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::DEV,
            'global_role' => GlobalUserRole::DEV,
            'sector_id' => null,
            'room_id' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
