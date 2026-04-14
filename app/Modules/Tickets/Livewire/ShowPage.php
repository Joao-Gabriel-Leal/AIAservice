<?php

namespace App\Modules\Tickets\Livewire;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Services\TicketWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ShowPage extends Component
{
    use AuthorizesRequests;

    public int $ticketId;

    public string $message = '';

    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->ticketId = $ticket->id;
    }

    public function updateFixedField(TicketWorkflowService $workflowService, string $field, mixed $value): void
    {
        $ticket = $this->ticket();
        $this->authorize('update', $ticket);

        if (! in_array($field, ['title', 'priority', 'ticket_status_id', 'ticket_group_id', 'assignee_id'], true)) {
            return;
        }

        $workflowService->updateTicket(auth()->user(), $ticket, [
            $field => $value === '' ? null : $value,
        ]);
    }

    public function updateDynamicField(TicketWorkflowService $workflowService, int $fieldId, mixed $value): void
    {
        $ticket = $this->ticket();
        $field = TicketField::query()->findOrFail($fieldId);

        $this->authorize('update', $ticket);
        $workflowService->updateField(auth()->user(), $ticket, $field, $value);
    }

    public function sendMessage(TicketWorkflowService $workflowService): void
    {
        $validated = $this->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $ticket = $this->ticket();
        $this->authorize('comment', $ticket);
        $workflowService->addMessage(auth()->user(), $ticket, $validated['message']);

        $this->reset('message');
    }

    public function render(): View
    {
        $ticket = $this->ticket();
        $board = $ticket->board()->with(['groups', 'statuses', 'fields.options'])->first();

        return view('livewire.tickets.show-page', [
            'ticket' => $ticket,
            'board' => $board,
            'fields' => $board?->fields->where('is_active', true)->values() ?? collect(),
            'statuses' => $board?->statuses ?? collect(),
            'groups' => $board?->groups ?? collect(),
            'assignees' => User::query()
                ->where('sector_id', $ticket->sector_id)
                ->whereIn('role', ['sector_admin', 'technician'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'sectorUsers' => User::query()
                ->where('sector_id', $ticket->sector_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'priorities' => TicketPriority::cases(),
        ])->layout('layouts.portal', [
            'title' => "Chamado #{$ticket->id}",
            'subtitle' => 'Detalhes, historico e conversa do chamado.',
        ]);
    }

    private function ticket(): Ticket
    {
        return Ticket::query()
            ->visibleTo(auth()->user())
            ->with([
                'sector.company',
                'room',
                'requester',
                'assignee',
                'status',
                'group',
                'catalogItem.form',
                'fieldValues.field.options',
                'messages.user',
                'attachments.uploader',
                'activityLogs.causer',
            ])
            ->findOrFail($this->ticketId);
    }
}
