<?php

namespace App\Modules\Tickets\Services;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Shared\Services\ActivityLogService;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketSlaPolicy;
use App\Modules\Tickets\Models\TicketSlaTarget;
use App\Modules\Tickets\Notifications\TicketSlaNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class TicketSlaService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function ensurePolicy(TicketBoard $board): TicketSlaPolicy
    {
        $policy = TicketSlaPolicy::query()->firstOrCreate(
            ['ticket_board_id' => $board->id],
            ['is_active' => true],
        );

        foreach ($this->defaultTargets() as $priority => $target) {
            TicketSlaTarget::query()->firstOrCreate(
                [
                    'ticket_sla_policy_id' => $policy->id,
                    'priority' => $priority,
                ],
                $target,
            );
        }

        return $policy->fresh('targets');
    }

    public function syncPolicy(TicketBoard $board, array $targets, bool $isActive = true): TicketSlaPolicy
    {
        $policy = $this->ensurePolicy($board);
        $policy->update(['is_active' => $isActive]);

        foreach (TicketPriority::cases() as $priority) {
            $values = $targets[$priority->value] ?? [];

            TicketSlaTarget::query()->updateOrCreate(
                [
                    'ticket_sla_policy_id' => $policy->id,
                    'priority' => $priority->value,
                ],
                [
                    'first_response_minutes' => $values['first_response_minutes'] ?? null,
                    'resolution_minutes' => $values['resolution_minutes'] ?? null,
                ],
            );
        }

        return $policy->fresh('targets');
    }

    public function applyPolicy(Ticket $ticket): Ticket
    {
        $ticket->loadMissing('board.slaPolicy.targets');

        $policy = $ticket->board?->slaPolicy;
        $target = $policy?->is_active
            ? $policy->targets->firstWhere('priority', $ticket->priority)
            : null;

        if (! $target) {
            return $ticket;
        }

        $createdAt = $ticket->created_at ?? now();

        $ticket->forceFill([
            'first_response_sla_minutes' => $target->first_response_minutes,
            'first_response_due_at' => $target->first_response_minutes ? $createdAt->copy()->addMinutes($target->first_response_minutes) : null,
            'first_response_warning_sent_at' => $ticket->first_responded_at ? $ticket->first_response_warning_sent_at : null,
            'first_response_breached_at' => $ticket->first_responded_at ? $ticket->first_response_breached_at : null,
            'resolution_sla_minutes' => $target->resolution_minutes,
            'resolution_due_at' => $target->resolution_minutes ? $createdAt->copy()->addMinutes($target->resolution_minutes) : null,
            'resolution_warning_sent_at' => $ticket->resolved_at ? $ticket->resolution_warning_sent_at : null,
            'resolution_breached_at' => $ticket->resolved_at ? $ticket->resolution_breached_at : null,
        ])->save();

        return $ticket->fresh();
    }

    public function captureFirstResponse(User $actor, Ticket $ticket, ?CarbonInterface $respondedAt = null): void
    {
        if (! $this->shouldCountAsFirstResponse($actor, $ticket) || $ticket->first_responded_at) {
            return;
        }

        $respondedAt ??= now();

        $ticket->forceFill([
            'first_responded_at' => $respondedAt,
        ])->save();

        if ($ticket->first_response_due_at && $respondedAt->greaterThan($ticket->first_response_due_at)) {
            $this->markFirstResponseBreached($ticket, $respondedAt, false);
        }
    }

    public function evaluateTicket(Ticket $ticket, ?CarbonInterface $checkedAt = null): void
    {
        $checkedAt ??= now();

        if (! $ticket->first_responded_at && $ticket->first_response_due_at) {
            $this->maybeWarnFirstResponse($ticket, $checkedAt);

            if ($checkedAt->greaterThan($ticket->first_response_due_at)) {
                $this->markFirstResponseBreached($ticket, $checkedAt, true);
            }
        }

        if (! $ticket->resolved_at && $ticket->resolution_due_at) {
            $this->maybeWarnResolution($ticket, $checkedAt);

            if ($checkedAt->greaterThan($ticket->resolution_due_at)) {
                $this->markResolutionBreached($ticket, $checkedAt, true);
            }
        }

        if ($ticket->resolved_at && $ticket->resolution_due_at && $ticket->resolved_at->greaterThan($ticket->resolution_due_at)) {
            $this->markResolutionBreached($ticket, $ticket->resolved_at, false);
        }
    }

    public function monitorOpenTickets(): void
    {
        Ticket::query()
            ->with(['requester', 'assignee'])
            ->where(function ($query) {
                $query
                    ->where(function ($subquery) {
                        $subquery->whereNull('first_responded_at')->whereNotNull('first_response_due_at');
                    })
                    ->orWhere(function ($subquery) {
                        $subquery->whereNull('resolved_at')->whereNotNull('resolution_due_at');
                    });
            })
            ->chunkById(100, fn ($tickets) => $tickets->each(fn (Ticket $ticket) => $this->evaluateTicket($ticket)));
    }

    private function shouldCountAsFirstResponse(User $actor, Ticket $ticket): bool
    {
        if ($actor->id === $ticket->requester_id) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $actor->hasOperationalAccess($ticket->sector_id);
    }

    private function maybeWarnFirstResponse(Ticket $ticket, CarbonInterface $checkedAt): void
    {
        if ($ticket->first_response_warning_sent_at || ! $ticket->first_response_due_at) {
            return;
        }

        if ($this->isInsideWarningWindow($checkedAt, $ticket->first_response_due_at, $ticket->first_response_sla_minutes)) {
            $this->sendNotification(
                $ticket,
                'SLA de primeira resposta proximo do vencimento',
                "O chamado #{$ticket->id} esta perto de estourar o SLA de primeira resposta.",
            );

            $ticket->forceFill(['first_response_warning_sent_at' => $checkedAt])->save();
        }
    }

    private function maybeWarnResolution(Ticket $ticket, CarbonInterface $checkedAt): void
    {
        if ($ticket->resolution_warning_sent_at || ! $ticket->resolution_due_at) {
            return;
        }

        if ($this->isInsideWarningWindow($checkedAt, $ticket->resolution_due_at, $ticket->resolution_sla_minutes)) {
            $this->sendNotification(
                $ticket,
                'SLA de resolucao proximo do vencimento',
                "O chamado #{$ticket->id} esta perto de estourar o SLA de resolucao.",
            );

            $ticket->forceFill(['resolution_warning_sent_at' => $checkedAt])->save();
        }
    }

    private function markFirstResponseBreached(Ticket $ticket, CarbonInterface $breachedAt, bool $notify): void
    {
        if ($ticket->first_response_breached_at) {
            return;
        }

        $ticket->forceFill(['first_response_breached_at' => $breachedAt])->save();

        $this->activityLogService->log(null, $ticket, 'ticket.sla.first_response_breached', 'SLA de primeira resposta violado.', [
            'sector_id' => $ticket->sector_id,
            'breached_at' => $breachedAt->toIso8601String(),
        ]);

        if ($notify) {
            $this->sendNotification(
                $ticket,
                'SLA de primeira resposta estourado',
                "O chamado #{$ticket->id} ultrapassou o SLA de primeira resposta.",
            );
        }
    }

    private function markResolutionBreached(Ticket $ticket, CarbonInterface $breachedAt, bool $notify): void
    {
        if ($ticket->resolution_breached_at) {
            return;
        }

        $ticket->forceFill(['resolution_breached_at' => $breachedAt])->save();

        $this->activityLogService->log(null, $ticket, 'ticket.sla.resolution_breached', 'SLA de resolucao violado.', [
            'sector_id' => $ticket->sector_id,
            'breached_at' => $breachedAt->toIso8601String(),
        ]);

        if ($notify) {
            $this->sendNotification(
                $ticket,
                'SLA de resolucao estourado',
                "O chamado #{$ticket->id} ultrapassou o SLA de resolucao.",
            );
        }
    }

    private function sendNotification(Ticket $ticket, string $title, string $message): void
    {
        $recipients = $this->notificationRecipients($ticket);

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new TicketSlaNotification($ticket, $title, $message));
        } catch (Throwable $throwable) {
            Log::warning('Ticket SLA notification delivery failed.', [
                'ticket_id' => $ticket->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function notificationRecipients(Ticket $ticket): Collection
    {
        return collect([
            $ticket->assignee,
            ...User::query()
                ->where(function ($query) use ($ticket) {
                    $query
                        ->where('global_role', 'super_admin')
                        ->orWhere(function ($scopedQuery) use ($ticket) {
                            $scopedQuery
                                ->withSectorAccess($ticket->sector_id, ['sector_admin', 'technician']);
                        });
                })
                ->get()
                ->all(),
        ])->filter()->unique('id')->values();
    }

    private function isInsideWarningWindow(CarbonInterface $checkedAt, CarbonInterface $dueAt, ?int $targetMinutes): bool
    {
        if (! $targetMinutes || $checkedAt->greaterThan($dueAt)) {
            return false;
        }

        $warningMinutes = max(5, (int) ceil($targetMinutes * 0.2));

        return $checkedAt->greaterThanOrEqualTo($dueAt->copy()->subMinutes($warningMinutes));
    }

    private function defaultTargets(): array
    {
        return [
            TicketPriority::LOW->value => [
                'first_response_minutes' => 240,
                'resolution_minutes' => 2880,
            ],
            TicketPriority::MEDIUM->value => [
                'first_response_minutes' => 120,
                'resolution_minutes' => 1440,
            ],
            TicketPriority::HIGH->value => [
                'first_response_minutes' => 60,
                'resolution_minutes' => 480,
            ],
            TicketPriority::URGENT->value => [
                'first_response_minutes' => 15,
                'resolution_minutes' => 240,
            ],
        ];
    }
}
