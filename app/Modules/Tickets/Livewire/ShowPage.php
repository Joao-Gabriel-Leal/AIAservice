<?php

namespace App\Modules\Tickets\Livewire;

use App\Enums\TicketPriority;
use App\Enums\TicketTimeEntryApprovalStatus;
use App\Enums\TicketTimeEntrySource;
use App\Models\User;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketTimeEntry;
use App\Modules\Tickets\Services\TicketWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithFileUploads;

class ShowPage extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    private const HELPFUL_FEEDBACK_COUNT_SQL = '(select count(*) from knowledge_base_article_feedback where knowledge_base_articles.id = knowledge_base_article_feedback.knowledge_base_article_id and is_helpful = true)';

    private const NOT_HELPFUL_FEEDBACK_COUNT_SQL = '(select count(*) from knowledge_base_article_feedback where knowledge_base_articles.id = knowledge_base_article_feedback.knowledge_base_article_id and is_helpful = false)';

    public int $ticketId;

    public string $message = '';

    public array $chatFiles = [];

    public ?int $ratingValue = null;

    public string $ratingComment = '';

    public bool $showTimeEntryForm = false;

    public ?int $editingTimeEntryId = null;

    /**
     * @var array{started_at:string,ended_at:string}
     */
    public array $timeEntryForm = [
        'started_at' => '',
        'ended_at' => '',
    ];

    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->ticketId = $ticket->id;
        $this->timeEntryForm = $this->defaultTimeEntryForm();
    }

    public function updateFixedField(TicketWorkflowService $workflowService, string $field, mixed $value): void
    {
        $ticket = $this->ticket();
        $this->authorize('update', $ticket);

        if (! in_array($field, ['title', 'priority', 'ticket_group_id', 'assignee_id'], true)) {
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
        $hasFiles = collect($this->chatFiles)->filter()->isNotEmpty();

        $validated = $this->validate([
            'message' => [$hasFiles ? 'nullable' : 'required', 'string', 'max:4000'],
            'chatFiles' => ['array', 'max:5'],
            'chatFiles.*' => ['file', 'max:25600'],
        ], [
            'message.required' => 'Escreva uma mensagem ou anexe ao menos um arquivo.',
            'chatFiles.max' => 'Envie no maximo 5 arquivos por mensagem.',
            'chatFiles.*.max' => 'Cada arquivo pode ter no maximo 25 MB.',
        ]);

        $message = trim((string) ($validated['message'] ?? ''));

        if ($message === '' && ! $hasFiles) {
            $this->addError('message', 'Escreva uma mensagem ou anexe ao menos um arquivo.');

            return;
        }

        $ticket = $this->ticket();
        $this->authorize('comment', $ticket);
        $workflowService->addMessage(auth()->user(), $ticket, $message, $this->chatFiles);

        $this->reset('message', 'chatFiles');
    }

    public function closeOwnTicket(TicketWorkflowService $workflowService): void
    {
        $ticket = $this->ticket();

        $this->authorize('closeOwn', $ticket);
        $workflowService->closeByRequester(auth()->user(), $ticket);

        session()->flash('status', 'Chamado finalizado com sucesso.');
    }

    public function reopenOwnTicket(TicketWorkflowService $workflowService): void
    {
        $ticket = $this->ticket();

        $this->authorize('reopenOwn', $ticket);
        $workflowService->reopenByRequester(auth()->user(), $ticket);

        session()->flash('status', 'Chamado reaberto com sucesso.');
    }

    public function submitRating(TicketWorkflowService $workflowService): void
    {
        $validated = $this->validate([
            'ratingValue' => ['required', 'integer', 'min:1', 'max:5'],
            'ratingComment' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticket = $this->ticket();

        $this->authorize('rate', $ticket);

        $workflowService->submitRating(auth()->user(), $ticket, [
            'rating' => $validated['ratingValue'],
            'comment' => $validated['ratingComment'],
        ]);

        $this->reset('ratingValue', 'ratingComment');
        session()->flash('status', 'Avaliacao registrada com sucesso.');
    }

    public function startTimeEntry(TicketWorkflowService $workflowService): void
    {
        $this->resetValidation('timeTracking');

        $ticket = $this->ticket();
        $this->authorize('trackTime', $ticket);

        $workflowService->startTimeEntry(auth()->user(), $ticket);

        session()->flash('status', 'Cronometro iniciado com sucesso.');
    }

    public function stopTimeEntry(TicketWorkflowService $workflowService, int $timeEntryId): void
    {
        $this->resetValidation('timeTracking');

        $timeEntry = $this->timeEntry($timeEntryId);
        $this->authorize('update', $timeEntry);

        $workflowService->stopTimeEntry(auth()->user(), $timeEntry);

        session()->flash('status', 'Cronometro encerrado com sucesso.');
    }

    public function openTimeEntryForm(): void
    {
        $ticket = $this->ticket();
        $this->authorize('trackTime', $ticket);

        $this->resetValidation();
        $this->editingTimeEntryId = null;
        $this->timeEntryForm = $this->defaultTimeEntryForm();
        $this->showTimeEntryForm = true;
    }

    public function editTimeEntry(int $timeEntryId): void
    {
        $timeEntry = $this->timeEntry($timeEntryId);
        $this->authorize('update', $timeEntry);

        $this->resetValidation();
        $this->editingTimeEntryId = $timeEntry->id;
        $this->timeEntryForm = [
            'started_at' => $this->toDateTimeLocalValue($timeEntry->started_at),
            'ended_at' => $this->toDateTimeLocalValue($timeEntry->ended_at),
        ];
        $this->showTimeEntryForm = true;
    }

    public function cancelTimeEntryForm(): void
    {
        $this->resetValidation();
        $this->showTimeEntryForm = false;
        $this->editingTimeEntryId = null;
        $this->timeEntryForm = $this->defaultTimeEntryForm();
    }

    public function saveTimeEntry(TicketWorkflowService $workflowService): void
    {
        $validated = $this->validate([
            'timeEntryForm.started_at' => ['required', 'date'],
            'timeEntryForm.ended_at' => ['required', 'date', 'after:timeEntryForm.started_at'],
        ], [
            'timeEntryForm.started_at.required' => 'Informe o inicio da sessao.',
            'timeEntryForm.started_at.date' => 'Informe um horario inicial valido.',
            'timeEntryForm.ended_at.required' => 'Informe o fim da sessao.',
            'timeEntryForm.ended_at.date' => 'Informe um horario final valido.',
            'timeEntryForm.ended_at.after' => 'O horario final precisa ser maior que o horario inicial.',
        ]);

        if ($this->editingTimeEntryId) {
            $timeEntry = $this->timeEntry($this->editingTimeEntryId);
            $this->authorize('update', $timeEntry);
            $workflowService->updateTimeEntry(auth()->user(), $timeEntry, $validated['timeEntryForm']);

            session()->flash('status', 'Sessao de tempo atualizada com sucesso.');
        } else {
            $ticket = $this->ticket();
            $this->authorize('trackTime', $ticket);
            $workflowService->createManualTimeEntry(auth()->user(), $ticket, $validated['timeEntryForm']);

            session()->flash('status', 'Sessao manual registrada com sucesso.');
        }

        $this->cancelTimeEntryForm();
    }

    public function deleteTimeEntry(TicketWorkflowService $workflowService, int $timeEntryId): void
    {
        $timeEntry = $this->timeEntry($timeEntryId);
        $this->authorize('delete', $timeEntry);

        $workflowService->deleteTimeEntry(auth()->user(), $timeEntry);

        if ($this->editingTimeEntryId === $timeEntryId) {
            $this->cancelTimeEntryForm();
        }

        session()->flash('status', 'Sessao de tempo removida com sucesso.');
    }

    public function approveTimeEntry(TicketWorkflowService $workflowService, int $timeEntryId): void
    {
        $timeEntry = $this->timeEntry($timeEntryId);
        $this->authorize('review', $timeEntry);

        $workflowService->approveTimeEntry(auth()->user(), $timeEntry);

        session()->flash('status', 'Apontamento manual aprovado com sucesso.');
    }

    public function rejectTimeEntry(TicketWorkflowService $workflowService, int $timeEntryId): void
    {
        $timeEntry = $this->timeEntry($timeEntryId);
        $this->authorize('review', $timeEntry);

        $workflowService->rejectTimeEntry(auth()->user(), $timeEntry);

        session()->flash('status', 'Apontamento manual rejeitado.');
    }

    public function render(): View
    {
        $ticket = $this->ticket();
        $board = $ticket->board()->with(['groups', 'fields.options'])->first();
        $assignees = $board ? $this->boardAssignees($board) : collect();
        $canRate = auth()->user()->can('rate', $ticket);
        $canComment = auth()->user()->can('comment', $ticket);
        $canViewTimeTracking = auth()->user()->can('viewTimeTracking', $ticket);
        $canTrackTime = auth()->user()->can('trackTime', $ticket);
        $canCloseOwn = auth()->user()->can('closeOwn', $ticket);
        $canReopenOwn = auth()->user()->can('reopenOwn', $ticket);
        $activeOwnTimeEntry = $canViewTimeTracking ? $ticket->activeTimeEntryForUser(auth()->user()) : null;
        $timeEntriesByUser = $canViewTimeTracking ? $ticket->timeEntriesTotalByUser() : collect();
        $pendingTimeEntriesCount = $canViewTimeTracking
            ? $ticket->timeEntries->filter(fn (TicketTimeEntry $timeEntry) => $timeEntry->isPendingApproval())->count()
            : 0;
        $timeTrackingPayload = $canViewTimeTracking
            ? $this->timeTrackingPayload($ticket)
            : [];
        $timeTrackingRenderKey = $canViewTimeTracking
            ? $this->timeTrackingRenderKey($ticket)
            : null;
        $canCreateKnowledgeArticle = auth()->user()->can('createFromTicket', [KnowledgeBaseArticle::class, $ticket]);
        $knowledgeArticle = $ticket->generatedKnowledgeBaseArticle;
        $knowledgeArticleIdsUsed = $ticket->knowledgeBaseUsages->pluck('knowledge_base_article_id')->all();
        $helpfulKnowledgeArticles = $ticket->isClosed() && auth()->user()->hasOperationalAccess($ticket->sector_id)
            ? KnowledgeBaseArticle::query()
                ->withCount([
                    'feedback as helpful_feedback_count' => fn ($query) => $query->where('is_helpful', true),
                    'feedback as not_helpful_feedback_count' => fn ($query) => $query->where('is_helpful', false),
                    'ticketUsages',
                ])
                ->where('sector_id', $ticket->sector_id)
                ->where('is_active', true)
                ->published()
                ->when($knowledgeArticle, fn ($query) => $query->where('id', '!=', $knowledgeArticle->id))
                ->orderByRaw('('.self::HELPFUL_FEEDBACK_COUNT_SQL.' - '.self::NOT_HELPFUL_FEEDBACK_COUNT_SQL.') desc')
                ->orderByDesc('ticket_usages_count')
                ->latest('updated_at')
                ->limit(4)
                ->get()
            : collect();

        return view('livewire.tickets.show-page', [
            'ticket' => $ticket,
            'board' => $board,
            'fields' => $board?->fields->where('is_active', true)->values() ?? collect(),
            'groups' => $board?->groups ?? collect(),
            'assignees' => $assignees,
            'sectorUsers' => User::query()
                ->withSectorAccess($ticket->sector_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'priorities' => TicketPriority::cases(),
            'canRate' => $canRate,
            'canComment' => $canComment,
            'canCloseOwn' => $canCloseOwn,
            'canReopenOwn' => $canReopenOwn,
            'canViewTimeTracking' => $canViewTimeTracking,
            'canTrackTime' => $canTrackTime,
            'activeOwnTimeEntry' => $activeOwnTimeEntry,
            'timeEntries' => $canViewTimeTracking ? $ticket->timeEntries : collect(),
            'timeEntriesByUser' => $timeEntriesByUser,
            'pendingTimeEntriesCount' => $pendingTimeEntriesCount,
            'timeTrackingPayload' => $timeTrackingPayload,
            'timeTrackingRenderKey' => $timeTrackingRenderKey,
            'canCreateKnowledgeArticle' => $canCreateKnowledgeArticle,
            'knowledgeArticle' => $knowledgeArticle,
            'knowledgeArticleIdsUsed' => $knowledgeArticleIdsUsed,
            'helpfulKnowledgeArticles' => $helpfulKnowledgeArticles,
        ])->layout('layouts.portal', [
            'title' => "Chamado #{$ticket->id}",
            'subtitle' => 'Detalhes, historico e conversa do chamado.',
        ]);
    }

    public function formatDuration(int $totalSeconds): string
    {
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function sourceLabel(TicketTimeEntrySource|string|null $source): string
    {
        if ($source instanceof TicketTimeEntrySource) {
            return $source->label();
        }

        return match ((string) $source) {
            TicketTimeEntrySource::MANUAL->value => TicketTimeEntrySource::MANUAL->label(),
            default => TicketTimeEntrySource::TIMER->label(),
        };
    }

    public function canUpdateTimeEntry(TicketTimeEntry $timeEntry): bool
    {
        return auth()->user()->can('update', $timeEntry);
    }

    public function canDeleteTimeEntry(TicketTimeEntry $timeEntry): bool
    {
        return auth()->user()->can('delete', $timeEntry);
    }

    public function canReviewTimeEntry(TicketTimeEntry $timeEntry): bool
    {
        return auth()->user()->can('review', $timeEntry);
    }

    public function approvalStatusLabel(TicketTimeEntry $timeEntry): string
    {
        return ($timeEntry->approval_status ?? TicketTimeEntryApprovalStatus::APPROVED)->label();
    }

    private function ticket(): Ticket
    {
        $ticket = Ticket::query()
            ->visibleTo(auth()->user())
            ->with([
                'sector.company',
                'requester',
                'assignee',
                'group',
                'status',
                'catalogItem.form',
                'fieldValues.field.options',
                'messages.user',
                'messages.attachments.uploader',
                'attachments.uploader',
                'activityLogs.causer',
                'rating.user',
                'generatedKnowledgeBaseArticle',
                'knowledgeBaseUsages',
            ])
            ->findOrFail($this->ticketId);

        if (auth()->user()->can('viewTimeTracking', $ticket)) {
            $ticket->load([
                'timeEntries' => fn ($query) => $query->with('user')->latest('started_at'),
            ]);
        }

        return $ticket;
    }

    private function timeEntry(int $timeEntryId): TicketTimeEntry
    {
        $timeEntry = TicketTimeEntry::query()
            ->with(['ticket', 'user'])
            ->where('ticket_id', $this->ticketId)
            ->findOrFail($timeEntryId);

        $this->authorize('view', $timeEntry);

        return $timeEntry;
    }

    private function boardAssignees($board)
    {
        $operatorIds = $board->operators()->pluck('users.id')->all();

        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($board, $operatorIds): void {
                $query->withSectorAccess($board->sector_id, ['sector_admin']);

                if ($operatorIds !== []) {
                    $query->orWhereIn('id', $operatorIds);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{ticketBaseSeconds:int,ticketActiveStartedAts:array<int,string>}
     */
    private function timeTrackingPayload(Ticket $ticket): array
    {
        $ticketBaseSeconds = $ticket->timeEntries
            ->filter(fn (TicketTimeEntry $timeEntry) => $timeEntry->countsTowardTotals())
            ->reject(fn (TicketTimeEntry $timeEntry) => $timeEntry->isRunning())
            ->sum(fn (TicketTimeEntry $timeEntry) => (int) $timeEntry->duration_seconds);

        return [
            'ticketBaseSeconds' => $ticketBaseSeconds,
            'ticketActiveStartedAts' => $ticket->timeEntries
                ->filter(fn (TicketTimeEntry $timeEntry) => $timeEntry->countsTowardTotals())
                ->filter(fn (TicketTimeEntry $timeEntry) => $timeEntry->isRunning())
                ->map(fn (TicketTimeEntry $timeEntry) => $timeEntry->started_at?->toIso8601String())
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function timeTrackingRenderKey(Ticket $ticket): string
    {
        $signature = $ticket->timeEntries
            ->map(fn (TicketTimeEntry $timeEntry) => [
                'id' => $timeEntry->id,
                'started_at' => $timeEntry->started_at?->toIso8601String(),
                'ended_at' => $timeEntry->ended_at?->toIso8601String(),
                'duration_seconds' => $timeEntry->duration_seconds,
                'source' => $timeEntry->source?->value ?? (string) $timeEntry->source,
                'approval_status' => $timeEntry->approval_status?->value ?? TicketTimeEntryApprovalStatus::APPROVED->value,
            ])
            ->values()
            ->all();

        return md5((string) json_encode($signature));
    }

    /**
     * @return array{started_at:string,ended_at:string}
     */
    private function defaultTimeEntryForm(): array
    {
        $endedAt = now()->startOfMinute();
        $startedAt = $endedAt->subHour();

        return [
            'started_at' => $this->toDateTimeLocalValue($startedAt),
            'ended_at' => $this->toDateTimeLocalValue($endedAt),
        ];
    }

    private function toDateTimeLocalValue($value): string
    {
        return $value?->format('Y-m-d\TH:i') ?? '';
    }
}
