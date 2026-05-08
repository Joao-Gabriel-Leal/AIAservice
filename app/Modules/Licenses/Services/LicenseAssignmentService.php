<?php

namespace App\Modules\Licenses\Services;

use App\Enums\LicenseAssignmentStatus;
use App\Models\User;
use App\Modules\Licenses\Models\License;
use App\Modules\Licenses\Models\LicenseAssignment;
use App\Modules\Shared\Services\ActivityLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LicenseAssignmentService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function createAssignment(License $license, array $validated, ?User $actor = null): LicenseAssignment
    {
        $payload = $this->normalizedPayload($validated, null);
        $status = $payload['status'];

        if ($status === LicenseAssignmentStatus::ACTIVE) {
            $this->ensureSeatAvailable($license);
            $this->ensureUniqueActiveAssignment($license, $payload['user_id'], $payload['assigned_email']);
        }

        return DB::transaction(function () use ($license, $payload, $actor) {
            $assignment = new LicenseAssignment($payload);

            $assignment->created_by = $actor?->id;

            $license->assignments()->save($assignment);

            $assignment = $assignment->fresh(['user', 'creator']);

            $this->activityLogService->log(
                $actor,
                $license,
                'license.assignment.created',
                'Licenca atribuida.',
                [
                    'license_id' => $license->id,
                    'assignment_id' => $assignment->id,
                    'sector_id' => $license->sector_id,
                    'assignment' => $this->assignmentPayload($assignment),
                ],
            );

            return $assignment;
        });
    }

    public function updateAssignment(License $license, LicenseAssignment $assignment, array $validated): LicenseAssignment
    {
        $this->assertBelongsToLicense($license, $assignment);

        $payload = $this->normalizedPayload($validated, $assignment);
        $targetStatus = $payload['status'];

        if ($assignment->status !== LicenseAssignmentStatus::ACTIVE && $targetStatus === LicenseAssignmentStatus::ACTIVE) {
            $this->ensureSeatAvailable($license);
        }

        if ($targetStatus === LicenseAssignmentStatus::ACTIVE) {
            $this->ensureUniqueActiveAssignment($license, $payload['user_id'], $payload['assigned_email'], $assignment->id);
        }

        return DB::transaction(function () use ($assignment, $payload) {
            $assignment->fill(Arr::except($payload, ['created_by']));
            $assignment->save();

            return $assignment->fresh(['user', 'creator']);
        });
    }

    public function transferAssignment(
        License $license,
        LicenseAssignment $assignment,
        array $validated,
        ?User $actor = null,
    ): LicenseAssignment {
        $this->assertBelongsToLicense($license, $assignment);

        if ($assignment->status !== LicenseAssignmentStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'assigned_email' => 'Somente licencas em uso podem ser transferidas.',
            ]);
        }

        return DB::transaction(function () use ($license, $assignment, $validated, $actor) {
            $before = $this->assignmentPayload($assignment->fresh(['user']));
            $user = $this->resolvedUser($validated['user_id'] ?? null);
            $assignedEmail = $this->resolvedAssignedEmailForTransfer($assignment, $validated, $user);
            $displayName = $this->resolvedDisplayNameForTransfer($assignment, $validated, $user, $assignedEmail);

            $this->ensureUniqueActiveAssignment($license, $user?->id, $assignedEmail, $assignment->id);

            $assignment->forceFill([
                'user_id' => $user?->id,
                'assigned_email' => $assignedEmail,
                'display_name' => $displayName,
                'external_reference' => array_key_exists('external_reference', $validated)
                    ? $this->normalizeString($validated['external_reference'])
                    : $assignment->external_reference,
                'status' => LicenseAssignmentStatus::ACTIVE,
                'assigned_at' => now(),
                'released_at' => null,
            ])->save();

            $assignment = $assignment->fresh(['user', 'creator']);

            $this->activityLogService->log(
                $actor,
                $license,
                'license.assignment.transferred',
                'Licenca transferida.',
                [
                    'license_id' => $license->id,
                    'assignment_id' => $assignment->id,
                    'sector_id' => $license->sector_id,
                    'from' => $before,
                    'to' => $this->assignmentPayload($assignment),
                ],
            );

            return $assignment;
        });
    }

    public function releaseAssignment(License $license, LicenseAssignment $assignment, ?User $actor = null): LicenseAssignment
    {
        $this->assertBelongsToLicense($license, $assignment);

        if ($assignment->status === LicenseAssignmentStatus::RELEASED) {
            return $assignment;
        }

        $assignment = DB::transaction(function () use ($license, $assignment, $actor) {
            $before = $this->assignmentPayload($assignment->fresh(['user']));

            $assignment->forceFill([
                'status' => LicenseAssignmentStatus::RELEASED,
                'released_at' => now(),
            ])->save();

            $assignment = $assignment->fresh(['user', 'creator']);

            $this->activityLogService->log(
                $actor,
                $license,
                'license.assignment.released',
                'Licenca desatribuida.',
                [
                    'license_id' => $license->id,
                    'assignment_id' => $assignment->id,
                    'sector_id' => $license->sector_id,
                    'assignment' => $before,
                ],
            );

            return $assignment;
        });

        return $assignment;
    }

    private function ensureSeatAvailable(License $license): void
    {
        $activeAssignments = $license->assignments()
            ->where('status', LicenseAssignmentStatus::ACTIVE->value)
            ->count();

        if ($activeAssignments >= $license->seats_total) {
            throw ValidationException::withMessages([
                'status' => 'Nao ha licencas disponiveis para uma nova atribuicao nesta licenca.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $validated, ?LicenseAssignment $assignment): array
    {
        $user = $this->resolvedUser($validated['user_id'] ?? null);

        $status = LicenseAssignmentStatus::from($validated['status'] ?? $assignment?->status?->value ?? LicenseAssignmentStatus::ACTIVE->value);
        $assignedEmail = $this->resolvedAssignedEmail($validated, $user);
        $displayName = $this->resolvedDisplayName($validated, $user, $assignedEmail);
        $assignedAt = $validated['assigned_at'] ?? $assignment?->assigned_at ?? now();
        $releasedAt = $status === LicenseAssignmentStatus::RELEASED
            ? ($validated['released_at'] ?? $assignment?->released_at ?? now())
            : null;

        return [
            'user_id' => $user?->id,
            'assigned_email' => $assignedEmail,
            'display_name' => $displayName,
            'external_reference' => $this->normalizeString($validated['external_reference'] ?? null),
            'seat_label' => $this->normalizeString($validated['seat_label'] ?? null),
            'status' => $status,
            'assigned_at' => $assignedAt,
            'released_at' => $releasedAt,
            'notes' => $this->normalizeString($validated['notes'] ?? null),
        ];
    }

    private function assertBelongsToLicense(License $license, LicenseAssignment $assignment): void
    {
        abort_unless($assignment->license_id === $license->id, 404);
    }

    private function ensureUniqueActiveAssignment(
        License $license,
        ?int $userId,
        ?string $assignedEmail,
        ?int $ignoreAssignmentId = null,
    ): void {
        if (! $userId && ! $assignedEmail) {
            return;
        }

        $email = $assignedEmail ? mb_strtolower($assignedEmail) : null;

        $duplicateExists = $license->assignments()
            ->where('status', LicenseAssignmentStatus::ACTIVE->value)
            ->when($ignoreAssignmentId, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->where(function ($query) use ($userId, $email) {
                if ($userId) {
                    $query->orWhere('user_id', $userId);
                }

                if ($email) {
                    $query->orWhereRaw('LOWER(assigned_email) = ?', [$email]);
                }
            })
            ->exists();

        if (! $duplicateExists) {
            return;
        }

        throw ValidationException::withMessages([
            $userId ? 'user_id' : 'assigned_email' => 'Esta pessoa ja possui uma atribuicao ativa nesta mesma licenca.',
        ]);
    }

    private function resolvedUser(mixed $userId): ?User
    {
        if (! $userId) {
            return null;
        }

        return User::query()
            ->where('is_active', true)
            ->find($userId);
    }

    private function resolvedAssignedEmail(array $validated, ?User $user): ?string
    {
        $explicitEmail = $this->normalizeString($validated['assigned_email'] ?? null);

        return $explicitEmail ?: $user?->email;
    }

    private function resolvedDisplayName(array $validated, ?User $user, ?string $assignedEmail): ?string
    {
        return $this->normalizeString($validated['display_name'] ?? null)
            ?: $user?->name
            ?: $assignedEmail;
    }

    private function resolvedAssignedEmailForTransfer(
        LicenseAssignment $assignment,
        array $validated,
        ?User $user,
    ): ?string {
        $explicitEmail = $this->normalizeString($validated['assigned_email'] ?? null);

        if (! $user) {
            return $explicitEmail;
        }

        $currentEmail = $assignment->resolvedAssignedEmail();

        if ($explicitEmail === null || $this->sameText($explicitEmail, $currentEmail)) {
            return $user->email;
        }

        return $explicitEmail;
    }

    private function resolvedDisplayNameForTransfer(
        LicenseAssignment $assignment,
        array $validated,
        ?User $user,
        ?string $assignedEmail,
    ): ?string {
        $explicitDisplayName = $this->normalizeString($validated['display_name'] ?? null);

        if (! $user) {
            return $explicitDisplayName ?: $assignedEmail;
        }

        $currentDisplayName = $assignment->resolvedDisplayName();

        if ($explicitDisplayName === null || $this->sameText($explicitDisplayName, $currentDisplayName)) {
            return $user->name ?: $assignedEmail;
        }

        return $explicitDisplayName;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function sameText(?string $left, ?string $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        return mb_strtolower($left) === mb_strtolower($right);
    }

    private function assignmentPayload(LicenseAssignment $assignment): array
    {
        return [
            'user_id' => $assignment->user_id,
            'display_name' => $assignment->resolvedDisplayName(),
            'assigned_email' => $assignment->resolvedAssignedEmail(),
            'external_reference' => $assignment->external_reference,
            'status' => $assignment->status?->value,
        ];
    }
}
