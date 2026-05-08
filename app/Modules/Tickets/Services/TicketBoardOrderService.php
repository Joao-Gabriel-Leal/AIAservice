<?php

namespace App\Modules\Tickets\Services;

use App\Modules\Tickets\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketBoardOrderService
{
    public function moveTicket(
        Ticket $ticket,
        ?int $sourceGroupId,
        ?int $targetGroupId,
        ?int $beforeTicketId = null,
        string $fallbackPlacement = 'top',
    ): void {
        $fallbackPlacement = $this->normalizeFallbackPlacement($fallbackPlacement);

        DB::transaction(function () use ($ticket, $sourceGroupId, $targetGroupId, $beforeTicketId, $fallbackPlacement): void {
            $lockedTicket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $sameGroup = $this->sameGroupId($sourceGroupId, $targetGroupId);

            $sourceIds = $this->groupTicketIds($lockedTicket->ticket_board_id, $sourceGroupId, $lockedTicket->id);
            $targetIds = $sameGroup
                ? $sourceIds
                : $this->groupTicketIds($lockedTicket->ticket_board_id, $targetGroupId, $lockedTicket->id);

            $insertIndex = $this->resolveInsertIndex($targetIds, $beforeTicketId, $fallbackPlacement);
            array_splice($targetIds, $insertIndex, 0, [$lockedTicket->id]);

            if (! $sameGroup) {
                $this->persistGroupOrder($sourceIds);
            }

            $this->persistGroupOrder($targetIds);
        });
    }

    private function groupTicketIds(int $boardId, ?int $groupId, int $exceptTicketId): array
    {
        return Ticket::query()
            ->where('ticket_board_id', $boardId)
            ->whereKeyNot($exceptTicketId)
            ->when(
                $groupId === null,
                fn ($query) => $query->whereNull('ticket_group_id'),
                fn ($query) => $query->where('ticket_group_id', $groupId),
            )
            ->lockForUpdate()
            ->orderedForBoardDisplay()
            ->pluck('id')
            ->all();
    }

    private function persistGroupOrder(array $ticketIds): void
    {
        foreach ($ticketIds as $index => $ticketId) {
            DB::table('tickets')
                ->where('id', $ticketId)
                ->update(['board_sort_order' => $index + 1]);
        }
    }

    private function resolveInsertIndex(array $targetIds, ?int $beforeTicketId, string $fallbackPlacement): int
    {
        if ($beforeTicketId !== null) {
            $beforeIndex = array_search($beforeTicketId, $targetIds, true);

            if ($beforeIndex !== false) {
                return $beforeIndex;
            }
        }

        return $fallbackPlacement === 'end'
            ? count($targetIds)
            : 0;
    }

    private function normalizeFallbackPlacement(string $fallbackPlacement): string
    {
        return in_array($fallbackPlacement, ['top', 'end'], true)
            ? $fallbackPlacement
            : 'top';
    }

    private function sameGroupId(?int $left, ?int $right): bool
    {
        return $left === $right;
    }
}
