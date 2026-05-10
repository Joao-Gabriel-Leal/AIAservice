<?php

namespace App\Modules\Tickets\Services;

use App\Models\User;
use App\Modules\Shared\Services\ActivityLogService;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MajorIncidentService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly TicketWorkflowService $ticketWorkflowService,
    ) {}

    public function markAsMajorIncident(User $actor, Ticket $incident): Ticket
    {
        $this->authorizeManage($actor, $incident);
        $incident = $incident->fresh(['group', 'status']) ?? $incident;

        if ($incident->major_incident_ticket_id !== null) {
            throw ValidationException::withMessages([
                'incident' => 'Um chamado vinculado a outro incidente nao pode virar incidente principal.',
            ]);
        }

        if ($incident->isClosed()) {
            throw ValidationException::withMessages([
                'incident' => 'Nao e possivel marcar um chamado encerrado como incidente massivo.',
            ]);
        }

        if ($incident->is_major_incident) {
            return $incident;
        }

        $incident->forceFill([
            'is_major_incident' => true,
            'last_activity_at' => now(),
        ])->save();

        $this->activityLogService->log($actor, $incident, 'ticket.major_incident.marked', 'Chamado marcado como incidente massivo.', [
            'sector_id' => $incident->sector_id,
        ]);

        return $incident->fresh(['incidentChildren', 'majorIncident']) ?? $incident;
    }

    public function unmarkMajorIncident(User $actor, Ticket $incident): Ticket
    {
        $this->authorizeManage($actor, $incident);
        $incident = $incident->fresh(['incidentChildren']) ?? $incident;

        if (! $incident->is_major_incident) {
            return $incident;
        }

        DB::transaction(function () use ($actor, $incident): void {
            $childIds = $incident->incidentChildren->pluck('id')->all();

            Ticket::query()
                ->whereIn('id', $childIds)
                ->update([
                    'major_incident_ticket_id' => null,
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]);

            $incident->forceFill([
                'is_major_incident' => false,
                'last_activity_at' => now(),
            ])->save();

            $this->activityLogService->log($actor, $incident, 'ticket.major_incident.unmarked', 'Chamado desmarcado como incidente massivo.', [
                'sector_id' => $incident->sector_id,
                'detached_ticket_ids' => $childIds,
            ]);
        });

        return $incident->fresh(['incidentChildren', 'majorIncident']) ?? $incident;
    }

    public function attachChild(User $actor, Ticket $incident, Ticket $child): Ticket
    {
        $this->authorizeManage($actor, $incident);
        $this->authorizeManage($actor, $child);

        $incident = $incident->fresh(['group', 'status']) ?? $incident;
        $child = $child->fresh(['group', 'status']) ?? $child;

        $this->validateLink($incident, $child);
        $incident = $this->markAsMajorIncident($actor, $incident);

        if ((int) $child->major_incident_ticket_id === (int) $incident->id) {
            return $incident->fresh(['incidentChildren']) ?? $incident;
        }

        DB::transaction(function () use ($actor, $incident, $child): void {
            $child->forceFill([
                'major_incident_ticket_id' => $incident->id,
                'last_activity_at' => now(),
            ])->save();

            $incident->forceFill(['last_activity_at' => now()])->save();

            $this->activityLogService->log($actor, $incident, 'ticket.major_incident.child_attached', 'Chamado vinculado ao incidente massivo.', [
                'sector_id' => $incident->sector_id,
                'child_ticket_id' => $child->id,
            ]);

            $this->activityLogService->log($actor, $child, 'ticket.major_incident.attached_to_parent', 'Chamado vinculado a um incidente massivo.', [
                'sector_id' => $child->sector_id,
                'major_incident_ticket_id' => $incident->id,
            ]);
        });

        return $incident->fresh(['incidentChildren']) ?? $incident;
    }

    public function detachChild(User $actor, Ticket $incident, Ticket $child): Ticket
    {
        $this->authorizeManage($actor, $incident);
        $this->authorizeManage($actor, $child);

        if ((int) $child->major_incident_ticket_id !== (int) $incident->id) {
            return $incident->fresh(['incidentChildren']) ?? $incident;
        }

        DB::transaction(function () use ($actor, $incident, $child): void {
            $child->forceFill([
                'major_incident_ticket_id' => null,
                'last_activity_at' => now(),
            ])->save();

            $incident->forceFill(['last_activity_at' => now()])->save();

            $this->activityLogService->log($actor, $incident, 'ticket.major_incident.child_detached', 'Chamado removido do incidente massivo.', [
                'sector_id' => $incident->sector_id,
                'child_ticket_id' => $child->id,
            ]);

            $this->activityLogService->log($actor, $child, 'ticket.major_incident.detached_from_parent', 'Chamado removido do incidente massivo.', [
                'sector_id' => $child->sector_id,
                'major_incident_ticket_id' => $incident->id,
            ]);
        });

        return $incident->fresh(['incidentChildren']) ?? $incident;
    }

    public function suggestions(User $actor, Ticket $source, int $limit = 6): Collection
    {
        if (! $actor->canOperateBoard($source->board)) {
            return collect();
        }

        $tokens = $this->titleTokens($source->title);

        if ($tokens === []) {
            return collect();
        }

        return Ticket::query()
            ->visibleTo($actor)
            ->where('ticket_board_id', $source->ticket_board_id)
            ->whereKeyNot($source->id)
            ->whereNull('major_incident_ticket_id')
            ->where('is_major_incident', false)
            ->whereNull('resolved_at')
            ->where('updated_at', '>=', now()->subHours(12))
            ->where(function (Builder $query) use ($tokens): void {
                foreach ($tokens as $token) {
                    $query->orWhereRaw('LOWER(title) LIKE ?', ['%'.$token.'%']);
                }
            })
            ->with(['requester', 'group', 'status'])
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->reject(fn (Ticket $ticket) => $ticket->isClosed())
            ->sortByDesc(fn (Ticket $ticket) => $this->titleSimilarityScore($source->title, $ticket->title, $tokens))
            ->take($limit)
            ->values();
    }

    public function sendMessage(User $actor, Ticket $incident, string $message, array $childIds = []): void
    {
        $incident = $this->majorIncidentForAction($actor, $incident);
        $message = trim($message);

        if ($message === '') {
            throw ValidationException::withMessages([
                'incidentBulkMessage' => 'Escreva uma mensagem para publicar no incidente.',
            ]);
        }

        $children = $this->selectedChildren($incident, $childIds, false);

        DB::transaction(function () use ($actor, $incident, $message, $children): void {
            $this->ticketWorkflowService->addMessage($actor, $incident, $message, context: [
                'source' => 'major_incident_message',
            ]);

            foreach ($children as $child) {
                $this->ticketWorkflowService->addMessage($actor, $child, $message, context: [
                    'source' => 'major_incident_child_message',
                    'major_incident_ticket_id' => $incident->id,
                ]);
            }
        });
    }

    public function closeChildren(User $actor, Ticket $incident, array $childIds, string $resolutionMessage): int
    {
        $incident = $this->majorIncidentForAction($actor, $incident);
        $resolutionMessage = trim($resolutionMessage);

        if ($resolutionMessage === '') {
            throw ValidationException::withMessages([
                'incidentResolutionMessage' => 'Informe a mensagem de solucao para fechar os chamados selecionados.',
            ]);
        }

        $children = $this->selectedChildren($incident, $childIds, true);

        if ($children->isEmpty()) {
            throw ValidationException::withMessages([
                'incidentSelectedChildIds' => 'Selecione ao menos um chamado vinculado para fechar.',
            ]);
        }

        $closedGroup = TicketGroup::query()
            ->where('ticket_board_id', $incident->ticket_board_id)
            ->where('is_active', true)
            ->where('is_closed', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $closedGroup) {
            throw ValidationException::withMessages([
                'incident' => 'Este quadro nao possui etapa final ativa para fechar os chamados vinculados.',
            ]);
        }

        DB::transaction(function () use ($actor, $incident, $children, $resolutionMessage, $closedGroup): void {
            foreach ($children as $child) {
                $this->ticketWorkflowService->addMessage($actor, $child, $resolutionMessage, context: [
                    'source' => 'major_incident_child_resolution',
                    'major_incident_ticket_id' => $incident->id,
                ]);

                $this->ticketWorkflowService->updateTicket($actor, $child, [
                    'ticket_group_id' => $closedGroup->id,
                ], [
                    'source' => 'major_incident_bulk_close',
                    'major_incident_ticket_id' => $incident->id,
                ]);
            }
        });

        return $children->count();
    }

    private function validateLink(Ticket $incident, Ticket $child): void
    {
        if ((int) $incident->id === (int) $child->id) {
            throw ValidationException::withMessages([
                'incident' => 'Um chamado nao pode ser vinculado a ele mesmo.',
            ]);
        }

        if ($incident->ticket_board_id !== $child->ticket_board_id) {
            throw ValidationException::withMessages([
                'incident' => 'O incidente e os chamados vinculados precisam estar no mesmo quadro.',
            ]);
        }

        if ($incident->major_incident_ticket_id !== null) {
            throw ValidationException::withMessages([
                'incident' => 'Um chamado vinculado a outro incidente nao pode receber filhos.',
            ]);
        }

        if ($child->is_major_incident) {
            throw ValidationException::withMessages([
                'incident' => 'Um incidente principal nao pode ser vinculado como filho de outro incidente.',
            ]);
        }

        if ($child->major_incident_ticket_id !== null && (int) $child->major_incident_ticket_id !== (int) $incident->id) {
            throw ValidationException::withMessages([
                'incident' => 'Este chamado ja esta vinculado a outro incidente massivo.',
            ]);
        }

        if ($child->isClosed()) {
            throw ValidationException::withMessages([
                'incident' => 'Nao e possivel vincular um chamado encerrado ao incidente massivo.',
            ]);
        }
    }

    private function authorizeManage(User $actor, Ticket $ticket): void
    {
        Gate::forUser($actor)->authorize('update', $ticket);
    }

    private function majorIncidentForAction(User $actor, Ticket $incident): Ticket
    {
        $this->authorizeManage($actor, $incident);
        $incident = $incident->fresh(['group', 'status']) ?? $incident;

        if (! $incident->is_major_incident || $incident->major_incident_ticket_id !== null) {
            throw ValidationException::withMessages([
                'incident' => 'Selecione um chamado principal marcado como incidente massivo.',
            ]);
        }

        if ($incident->isClosed()) {
            throw ValidationException::withMessages([
                'incident' => 'Nao e possivel executar acoes em massa em um incidente encerrado.',
            ]);
        }

        return $incident;
    }

    private function selectedChildren(Ticket $incident, array $childIds, bool $requireOpen): Collection
    {
        $ids = collect($childIds)
            ->map(fn (mixed $childId) => (int) $childId)
            ->filter(fn (int $childId) => $childId > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $children = Ticket::query()
            ->where('major_incident_ticket_id', $incident->id)
            ->whereIn('id', $ids->all())
            ->with(['group', 'status'])
            ->get();

        if ($children->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'incidentSelectedChildIds' => 'Algum chamado selecionado nao pertence a este incidente.',
            ]);
        }

        if ($requireOpen && $children->contains(fn (Ticket $child) => $child->isClosed())) {
            throw ValidationException::withMessages([
                'incidentSelectedChildIds' => 'Remova da selecao os chamados que ja estao encerrados.',
            ]);
        }

        return $children;
    }

    private function titleTokens(string $title): array
    {
        return Str::of($title)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->explode(' ')
            ->map(fn (string $token) => trim($token))
            ->filter(fn (string $token) => strlen($token) >= 4)
            ->unique()
            ->take(6)
            ->values()
            ->all();
    }

    private function titleSimilarityScore(string $sourceTitle, string $candidateTitle, array $tokens): int
    {
        $candidate = Str::of($candidateTitle)->ascii()->lower()->toString();
        $score = 0;

        foreach ($tokens as $token) {
            if (str_contains($candidate, $token)) {
                $score += strlen($token);
            }
        }

        similar_text(
            Str::of($sourceTitle)->ascii()->lower()->toString(),
            $candidate,
            $percent,
        );

        return $score + (int) round($percent);
    }
}
