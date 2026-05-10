<?php

namespace App\Modules\Tickets\Services;

use App\Enums\TicketBoardWorkflowMode;
use App\Enums\TicketSprintItemResult;
use App\Enums\TicketSprintStatus;
use App\Models\User;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketSprint;
use App\Modules\Tickets\Models\TicketSprintItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TicketSprintService
{
    public function createSprint(User $actor, TicketBoard $board, array $attributes): TicketSprint
    {
        Gate::forUser($actor)->authorize('update', $board);
        $this->assertDevelopmentBoard($board);

        return $board->sprints()->create([
            'name' => trim((string) $attributes['name']),
            'goal' => filled($attributes['goal'] ?? null) ? trim((string) $attributes['goal']) : null,
            'starts_at' => $attributes['starts_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
            'status' => TicketSprintStatus::PLANNED,
            'created_by_id' => $actor->id,
        ]);
    }

    public function startSprint(User $actor, TicketSprint $sprint): TicketSprint
    {
        $sprint = $sprint->loadMissing('board');
        Gate::forUser($actor)->authorize('update', $sprint->board);
        $this->assertDevelopmentBoard($sprint->board);

        if ($sprint->status === TicketSprintStatus::CLOSED) {
            throw ValidationException::withMessages([
                'sprint' => 'Sprints fechadas nao podem ser iniciadas.',
            ]);
        }

        $activeExists = TicketSprint::query()
            ->where('ticket_board_id', $sprint->ticket_board_id)
            ->where('status', TicketSprintStatus::ACTIVE->value)
            ->whereKeyNot($sprint->id)
            ->exists();

        if ($activeExists) {
            throw ValidationException::withMessages([
                'sprint' => 'Este quadro ja possui uma sprint ativa.',
            ]);
        }

        $sprint->update(['status' => TicketSprintStatus::ACTIVE]);

        return $sprint->fresh(['tickets', 'sprintItems']);
    }

    public function assignTicket(User $actor, Ticket $ticket, ?TicketSprint $targetSprint): Ticket
    {
        $ticket = $ticket->loadMissing(['board', 'sprint']);
        Gate::forUser($actor)->authorize('update', $ticket->board);
        $this->assertDevelopmentBoard($ticket->board);

        if ($ticket->isSubelement()) {
            throw ValidationException::withMessages([
                'sprint' => 'Subelementos acompanham o chamado pai e nao entram no planejamento da sprint.',
            ]);
        }

        if ($targetSprint) {
            $targetSprint = $targetSprint->loadMissing('board');

            if ((int) $targetSprint->ticket_board_id !== (int) $ticket->ticket_board_id) {
                throw ValidationException::withMessages([
                    'sprint' => 'A sprint precisa pertencer ao mesmo quadro do ticket.',
                ]);
            }

            if ($targetSprint->status === TicketSprintStatus::CLOSED) {
                throw ValidationException::withMessages([
                    'sprint' => 'Nao e possivel mover tickets para uma sprint fechada.',
                ]);
            }
        }

        return DB::transaction(function () use ($ticket, $targetSprint): Ticket {
            $previousSprintId = $ticket->ticket_sprint_id;

            if ($previousSprintId && (int) $previousSprintId !== (int) ($targetSprint?->id ?? 0)) {
                $this->markRemovedFromSprint((int) $previousSprintId, $ticket->id);
            }

            $ticket->forceFill([
                'ticket_sprint_id' => $targetSprint?->id,
                'sprint_sort_order' => $targetSprint ? $this->nextSprintSortOrder($targetSprint->id) : null,
                'last_activity_at' => now(),
            ])->save();

            if ($targetSprint) {
                $this->ensureSprintItem($targetSprint->id, $ticket->id);
            }

            return $ticket->fresh(['sprint', 'sprintItems']);
        });
    }

    public function closeSprint(User $actor, TicketSprint $sprint, string $destination, ?TicketSprint $targetSprint = null): TicketSprint
    {
        $sprint = $sprint->loadMissing('board');
        Gate::forUser($actor)->authorize('update', $sprint->board);
        $this->assertDevelopmentBoard($sprint->board);

        if ($sprint->status !== TicketSprintStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'sprint' => 'Somente a sprint ativa pode ser fechada.',
            ]);
        }

        $destination = in_array($destination, ['backlog', 'sprint'], true) ? $destination : '';

        if ($destination === '') {
            throw ValidationException::withMessages([
                'closeSprintForm.destination' => 'Escolha o destino dos itens abertos.',
            ]);
        }

        if ($destination === 'sprint') {
            if (! $targetSprint || (int) $targetSprint->ticket_board_id !== (int) $sprint->ticket_board_id) {
                throw ValidationException::withMessages([
                    'closeSprintForm.target_sprint_id' => 'Escolha uma sprint planejada do mesmo quadro.',
                ]);
            }

            if ($targetSprint->status !== TicketSprintStatus::PLANNED) {
                throw ValidationException::withMessages([
                    'closeSprintForm.target_sprint_id' => 'O destino precisa ser uma sprint planejada.',
                ]);
            }
        }

        return DB::transaction(function () use ($sprint, $destination, $targetSprint): TicketSprint {
            $tickets = Ticket::query()
                ->where('ticket_sprint_id', $sprint->id)
                ->topLevel()
                ->with(['group', 'status'])
                ->orderBy('sprint_sort_order')
                ->orderBy('id')
                ->get();

            foreach ($tickets as $ticket) {
                $completed = $ticket->isClosed();
                $result = $completed ? TicketSprintItemResult::COMPLETED : TicketSprintItemResult::CARRIED_OVER;
                $moveToSprintId = $completed || $destination === 'backlog' ? null : $targetSprint?->id;

                $this->ensureSprintItem($sprint->id, $ticket->id, [
                    'result' => $result,
                    'completed_at' => $completed ? ($ticket->resolved_at ?? now()) : null,
                    'moved_to_sprint_id' => $moveToSprintId,
                ]);

                if (! $completed) {
                    $ticket->forceFill([
                        'ticket_sprint_id' => $moveToSprintId,
                        'sprint_sort_order' => $moveToSprintId ? $this->nextSprintSortOrder($moveToSprintId) : null,
                        'last_activity_at' => now(),
                    ])->save();

                    if ($moveToSprintId) {
                        $this->ensureSprintItem($moveToSprintId, $ticket->id);
                    }
                }
            }

            $sprint->update([
                'status' => TicketSprintStatus::CLOSED,
                'closed_at' => now(),
            ]);

            return $sprint->fresh(['tickets', 'sprintItems.ticket.group', 'sprintItems.ticket.sprint']);
        });
    }

    private function assertDevelopmentBoard(TicketBoard $board): void
    {
        if ($board->workflow_mode !== TicketBoardWorkflowMode::DEVELOPMENT) {
            throw ValidationException::withMessages([
                'board' => 'Sprints estao disponiveis apenas em quadros de desenvolvimento.',
            ]);
        }
    }

    private function ensureSprintItem(int $sprintId, int $ticketId, array $attributes = []): void
    {
        $item = TicketSprintItem::query()->firstOrNew([
            'ticket_sprint_id' => $sprintId,
            'ticket_id' => $ticketId,
        ]);

        if (! $item->exists) {
            $item->added_at = now();
        }

        $item->fill([
            'result' => $attributes['result'] ?? null,
            'completed_at' => $attributes['completed_at'] ?? null,
            'moved_to_sprint_id' => $attributes['moved_to_sprint_id'] ?? null,
        ])->save();
    }

    private function markRemovedFromSprint(int $sprintId, int $ticketId): void
    {
        $sprintStatus = TicketSprint::query()->whereKey($sprintId)->value('status');

        if ($sprintStatus === TicketSprintStatus::CLOSED->value) {
            return;
        }

        $this->ensureSprintItem($sprintId, $ticketId, [
            'result' => TicketSprintItemResult::REMOVED,
        ]);
    }

    private function nextSprintSortOrder(int $sprintId): int
    {
        return ((int) Ticket::query()
            ->where('ticket_sprint_id', $sprintId)
            ->max('sprint_sort_order')) + 1;
    }
}
