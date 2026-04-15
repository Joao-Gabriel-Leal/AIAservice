<?php

namespace App\Modules\Assets\Services;

use App\Enums\AssetMovementType;
use App\Enums\AssetStatus;
use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetMovement;
use App\Modules\Rooms\Models\Room;
use App\Modules\Shared\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssetMovementService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    public function register(array $data, User $actor): Asset
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->assertRoomBelongsToSector((int) $data['current_sector_id'], (int) $data['current_room_id']);
            $this->assertUserMatchesSector($data['current_user_id'] ?? null, (int) $data['current_sector_id']);

            $asset = Asset::query()->create([
                'uuid' => (string) Str::uuid(),
                'asset_code' => null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'status' => AssetStatus::from((string) $data['status']),
                'current_sector_id' => (int) $data['current_sector_id'],
                'current_room_id' => (int) $data['current_room_id'],
                'current_user_id' => filled($data['current_user_id'] ?? null) ? (int) $data['current_user_id'] : null,
                'created_by' => $actor->id,
            ]);

            $asset->forceFill([
                'asset_code' => $this->assetCodeFor($asset),
            ])->save();

            $movement = $asset->movements()->create([
                'type' => AssetMovementType::CADASTRO_INICIAL,
                'to_sector_id' => $asset->current_sector_id,
                'to_room_id' => $asset->current_room_id,
                'to_user_id' => $asset->current_user_id,
                'to_status' => $asset->status,
                'moved_by' => $actor->id,
                'reason' => 'Cadastro inicial',
                'moved_at' => now(),
            ]);

            $this->activityLogService->log(
                $actor,
                $asset,
                'asset.created',
                'Patrimonio cadastrado no sistema.',
                [
                    'sector_id' => $asset->current_sector_id,
                    'asset_code' => $asset->asset_code,
                    'movement_id' => $movement->id,
                    'movement_type' => $movement->type?->value,
                    'to' => $this->movementTargetPayload($asset),
                ],
            );

            return $asset->fresh(['currentSector', 'currentRoom', 'currentUser']);
        });
    }

    public function move(Asset $asset, array $data, User $actor): AssetMovement
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $this->assertCanMove($asset);
            $this->assertRoomBelongsToSector((int) $data['current_sector_id'], (int) $data['current_room_id']);
            $this->assertUserMatchesSector($data['current_user_id'] ?? null, (int) $data['current_sector_id']);

            $targetStatus = AssetStatus::from((string) $data['status']);
            $targetSectorId = (int) $data['current_sector_id'];
            $targetRoomId = (int) $data['current_room_id'];
            $targetUserId = filled($data['current_user_id'] ?? null) ? (int) $data['current_user_id'] : null;

            if (
                $asset->current_sector_id === $targetSectorId
                && $asset->current_room_id === $targetRoomId
                && $asset->current_user_id === $targetUserId
                && $asset->status === $targetStatus
            ) {
                throw ValidationException::withMessages([
                    'current_sector_id' => 'Informe ao menos uma alteracao para registrar a movimentacao.',
                ]);
            }

            $movementType = $this->resolveMovementType($asset, $targetSectorId, $targetRoomId, $targetUserId, $targetStatus);

            $movement = $asset->movements()->create([
                'type' => $movementType,
                'from_sector_id' => $asset->current_sector_id,
                'from_room_id' => $asset->current_room_id,
                'from_user_id' => $asset->current_user_id,
                'from_status' => $asset->status,
                'to_sector_id' => $targetSectorId,
                'to_room_id' => $targetRoomId,
                'to_user_id' => $targetUserId,
                'to_status' => $targetStatus,
                'moved_by' => $actor->id,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'moved_at' => now(),
            ]);

            $asset->update([
                'status' => $targetStatus,
                'current_sector_id' => $targetSectorId,
                'current_room_id' => $targetRoomId,
                'current_user_id' => $targetUserId,
            ]);

            $this->activityLogService->log(
                $actor,
                $asset->fresh(),
                'asset.movement.created',
                'Movimentacao de patrimonio registrada.',
                [
                    'sector_id' => $targetSectorId,
                    'movement_id' => $movement->id,
                    'movement_type' => $movement->type?->value,
                    'from' => [
                        'sector_id' => $movement->from_sector_id,
                        'room_id' => $movement->from_room_id,
                        'user_id' => $movement->from_user_id,
                        'status' => $movement->from_status?->value,
                    ],
                    'to' => [
                        'sector_id' => $movement->to_sector_id,
                        'room_id' => $movement->to_room_id,
                        'user_id' => $movement->to_user_id,
                        'status' => $movement->to_status?->value,
                    ],
                ],
            );

            return $movement->fresh([
                'asset',
                'fromSector',
                'fromRoom',
                'fromUser',
                'toSector',
                'toRoom',
                'toUser',
                'movedBy',
            ]);
        });
    }

    private function assetCodeFor(Asset $asset): string
    {
        return 'PAT-'.str_pad((string) $asset->id, 6, '0', STR_PAD_LEFT);
    }

    private function assertRoomBelongsToSector(int $sectorId, int $roomId): void
    {
        $matches = Room::query()
            ->whereKey($roomId)
            ->where('sector_id', $sectorId)
            ->exists();

        if (! $matches) {
            throw ValidationException::withMessages([
                'current_room_id' => 'A sala selecionada nao pertence ao setor informado.',
            ]);
        }
    }

    private function assertUserMatchesSector(mixed $userId, int $sectorId): void
    {
        if (! filled($userId)) {
            return;
        }

        $user = User::query()->find((int) $userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'current_user_id' => 'O colaborador informado nao foi encontrado.',
            ]);
        }

        if (! $user->isSuperAdmin() && ! $user->hasSectorAccess($sectorId)) {
            throw ValidationException::withMessages([
                'current_user_id' => 'O colaborador precisa ter acesso ao setor de destino.',
            ]);
        }
    }

    private function resolveMovementType(
        Asset $asset,
        int $targetSectorId,
        int $targetRoomId,
        ?int $targetUserId,
        AssetStatus $targetStatus,
    ): AssetMovementType {
        if (
            $asset->current_sector_id === $targetSectorId
            && $asset->current_room_id === $targetRoomId
            && $asset->current_user_id === $targetUserId
            && $asset->status !== $targetStatus
        ) {
            return AssetMovementType::MUDANCA_STATUS;
        }

        if (
            $asset->current_sector_id !== $targetSectorId
            || $asset->current_room_id !== $targetRoomId
        ) {
            return AssetMovementType::TRANSFERENCIA_LOCAL;
        }

        if ($asset->current_user_id !== $targetUserId) {
            return $targetUserId === null
                ? AssetMovementType::DEVOLUCAO_COLABORADOR
                : AssetMovementType::ATRIBUICAO_COLABORADOR;
        }

        return AssetMovementType::TRANSFERENCIA_LOCAL;
    }

    private function movementTargetPayload(Asset $asset): array
    {
        return [
            'sector_id' => $asset->current_sector_id,
            'room_id' => $asset->current_room_id,
            'user_id' => $asset->current_user_id,
            'status' => $asset->status?->value,
        ];
    }

    private function assertCanMove(Asset $asset): void
    {
        if ($asset->status === AssetStatus::BAIXADO) {
            throw ValidationException::withMessages([
                'status' => 'Patrimonios baixados nao podem receber novas movimentacoes.',
            ]);
        }
    }
}
