<?php

namespace App\Modules\Tickets\Livewire;

use App\Enums\TicketPriority;
use App\Enums\TicketTimeEntryApprovalStatus;
use App\Enums\TicketTimeEntrySource;
use App\Models\User;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketFieldOption;
use App\Modules\Tickets\Models\TicketMessageTemplate;
use App\Modules\Tickets\Models\TicketTimeEntry;
use App\Modules\Tickets\Services\MajorIncidentService;
use App\Modules\Tickets\Services\TicketWorkflowService;
use App\Modules\Tickets\Support\TicketAttachmentRules;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
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

    public string $internalMessage = '';

    public array $internalChatFiles = [];

    public array $internalMentionedUserIds = [];

    public string $incidentBulkMessage = '';

    public string $incidentResolutionMessage = '';

    public array $incidentSelectedChildIds = [];

    public array $incidentSuggestedTicketIds = [];

    public string $incidentSelectionSignature = '';

    public string $newSubelementTitle = '';

    public array $personalTemplateForm = [
        'channel' => 'public',
        'name' => '',
        'body' => '',
    ];

    public ?int $editingPersonalTemplateId = null;

    public bool $showPersonalTemplateForm = false;

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

    public function createSubelement(TicketWorkflowService $workflowService): void
    {
        $ticket = $this->ticket();
        $this->authorize('update', $ticket);

        $title = trim($this->newSubelementTitle);

        if ($title === '') {
            $this->addError('newSubelementTitle', 'Informe um titulo para o subelemento.');

            return;
        }

        $workflowService->createSubelement(auth()->user(), $ticket, $title, [
            'source' => 'ticket_detail',
        ]);

        $this->newSubelementTitle = '';
        $this->resetErrorBag('newSubelementTitle');
        session()->flash('status', 'Subelemento criado com sucesso.');
    }

    public function updateSubelementFixedField(TicketWorkflowService $workflowService, int $subelementId, string $field, mixed $value): void
    {
        $subelement = $this->subelement($subelementId);
        $this->authorize('update', $subelement);

        if (! in_array($field, ['title', 'priority', 'ticket_group_id', 'assignee_id'], true)) {
            return;
        }

        $workflowService->updateTicket(auth()->user(), $subelement, [
            $field => $value === '' ? null : $value,
        ]);
    }

    public function updateSubelementDynamicField(TicketWorkflowService $workflowService, int $subelementId, int $fieldId, mixed $value): void
    {
        $subelement = $this->subelement($subelementId);
        $field = TicketField::query()->findOrFail($fieldId);

        $this->authorize('update', $subelement);
        $workflowService->updateField(auth()->user(), $subelement, $field, $value);
    }

    public function sendMessage(TicketWorkflowService $workflowService): void
    {
        $hasFiles = collect($this->chatFiles)->filter()->isNotEmpty();

        $validated = $this->validate([
            'message' => [$hasFiles ? 'nullable' : 'required', 'string', 'max:4000'],
            ...TicketAttachmentRules::validationRules('chatFiles'),
        ], [
            'message.required' => 'Escreva uma mensagem ou anexe ao menos um arquivo.',
            ...TicketAttachmentRules::validationMessages('chatFiles'),
        ]);

        $message = trim((string) ($validated['message'] ?? ''));
        $attachments = TicketAttachmentRules::validate($validated['chatFiles'] ?? [], 'chatFiles');

        if ($message === '' && $attachments === []) {
            $this->addError('message', 'Escreva uma mensagem ou anexe ao menos um arquivo.');

            return;
        }

        $ticket = $this->ticket();
        $this->authorize('comment', $ticket);
        $workflowService->addMessage(auth()->user(), $ticket, $message, $attachments);

        $this->reset('message', 'chatFiles');
    }

    public function sendInternalUpdate(TicketWorkflowService $workflowService): void
    {
        $hasFiles = collect($this->internalChatFiles)->filter()->isNotEmpty();

        $validated = $this->validate([
            'internalMessage' => [$hasFiles ? 'nullable' : 'required', 'string', 'max:4000'],
            'internalMentionedUserIds' => ['nullable', 'array'],
            'internalMentionedUserIds.*' => ['integer'],
            ...TicketAttachmentRules::validationRules('internalChatFiles'),
        ], [
            'internalMessage.required' => 'Escreva uma atualizacao interna ou anexe ao menos um arquivo.',
            ...TicketAttachmentRules::validationMessages('internalChatFiles'),
        ]);

        $message = trim((string) ($validated['internalMessage'] ?? ''));
        $attachments = TicketAttachmentRules::validate($validated['internalChatFiles'] ?? [], 'internalChatFiles');

        if ($message === '' && $attachments === []) {
            $this->addError('internalMessage', 'Escreva uma atualizacao interna ou anexe ao menos um arquivo.');

            return;
        }

        $ticket = $this->ticket();
        $this->authorize('commentInternally', $ticket);

        $workflowService->addMessage(
            auth()->user(),
            $ticket,
            $message,
            $attachments,
            isInternal: true,
            mentionedUserIds: $this->normalizeInternalMentionedUserIds($ticket, $validated['internalMentionedUserIds'] ?? []),
        );

        $this->reset('internalMessage', 'internalChatFiles', 'internalMentionedUserIds');
    }

    public function toggleMajorIncident(MajorIncidentService $majorIncidentService): void
    {
        $ticket = $this->ticket();
        $this->authorize('update', $ticket);

        if ($ticket->is_major_incident) {
            $majorIncidentService->unmarkMajorIncident(auth()->user(), $ticket);
            $this->resetIncidentSelections();
            session()->flash('status', 'Chamado desmarcado como incidente massivo.');

            return;
        }

        $majorIncidentService->markAsMajorIncident(auth()->user(), $ticket);
        $this->resetIncidentSelections();
        session()->flash('status', 'Chamado marcado como incidente massivo.');
    }

    public function linkIncidentChild(MajorIncidentService $majorIncidentService, int $childId): void
    {
        $ticket = $this->ticket();
        $this->authorize('update', $ticket);

        $child = Ticket::query()
            ->visibleTo(auth()->user())
            ->findOrFail($childId);

        $majorIncidentService->attachChild(auth()->user(), $ticket, $child);
        $this->resetIncidentSelections();
        session()->flash('status', 'Chamado vinculado ao incidente massivo.');
    }

    public function linkSelectedIncidentChildren(MajorIncidentService $majorIncidentService): void
    {
        $validated = $this->validate([
            'incidentSuggestedTicketIds' => ['required', 'array', 'min:1'],
            'incidentSuggestedTicketIds.*' => ['integer'],
        ], [
            'incidentSuggestedTicketIds.required' => 'Selecione ao menos um chamado para vincular.',
            'incidentSuggestedTicketIds.min' => 'Selecione ao menos um chamado para vincular.',
        ]);

        $ticket = $this->ticket();
        $this->authorize('update', $ticket);

        $childIds = collect($validated['incidentSuggestedTicketIds'])
            ->map(fn (mixed $childId) => (int) $childId)
            ->filter()
            ->unique()
            ->values();

        foreach ($childIds as $childId) {
            $child = Ticket::query()
                ->visibleTo(auth()->user())
                ->findOrFail($childId);

            $majorIncidentService->attachChild(auth()->user(), $ticket->fresh() ?? $ticket, $child);
        }

        $this->incidentSuggestedTicketIds = [];
        $this->resetIncidentSelections();
        session()->flash('status', $childIds->count().' chamado(s) vinculado(s).');
    }

    public function unlinkIncidentChild(MajorIncidentService $majorIncidentService, int $childId): void
    {
        $ticket = $this->ticket();
        $this->authorize('update', $ticket);

        $child = Ticket::query()
            ->visibleTo(auth()->user())
            ->with('majorIncident')
            ->findOrFail($childId);
        $incident = $ticket->is_major_incident ? $ticket : $child->majorIncident;

        abort_unless($incident, 404);

        $majorIncidentService->detachChild(auth()->user(), $incident, $child);
        $this->resetIncidentSelections();
        session()->flash('status', 'Chamado removido do incidente massivo.');
    }

    public function sendIncidentBulkMessage(MajorIncidentService $majorIncidentService): void
    {
        $validated = $this->validate([
            'incidentBulkMessage' => ['required', 'string', 'max:4000'],
            'incidentSelectedChildIds' => ['array'],
            'incidentSelectedChildIds.*' => ['integer'],
        ], [
            'incidentBulkMessage.required' => 'Escreva uma mensagem para publicar no incidente.',
        ]);

        $ticket = $this->ticket();
        $this->authorize('update', $ticket);

        $majorIncidentService->sendMessage(
            auth()->user(),
            $ticket,
            $validated['incidentBulkMessage'],
            $validated['incidentSelectedChildIds'] ?? [],
        );

        $this->reset('incidentBulkMessage');
        session()->flash('status', 'Atualizacao publicada no incidente massivo.');
    }

    public function closeIncidentChildren(MajorIncidentService $majorIncidentService): void
    {
        $validated = $this->validate([
            'incidentResolutionMessage' => ['required', 'string', 'max:4000'],
            'incidentSelectedChildIds' => ['required', 'array', 'min:1'],
            'incidentSelectedChildIds.*' => ['integer'],
        ], [
            'incidentResolutionMessage.required' => 'Informe a mensagem de solucao para fechar os chamados selecionados.',
            'incidentSelectedChildIds.required' => 'Selecione ao menos um chamado vinculado para fechar.',
            'incidentSelectedChildIds.min' => 'Selecione ao menos um chamado vinculado para fechar.',
        ]);

        $ticket = $this->ticket();
        $this->authorize('update', $ticket);

        $closedCount = $majorIncidentService->closeChildren(
            auth()->user(),
            $ticket,
            $validated['incidentSelectedChildIds'],
            $validated['incidentResolutionMessage'],
        );

        $this->reset('incidentResolutionMessage');
        $this->resetIncidentSelections();
        session()->flash('status', "{$closedCount} chamado(s) vinculado(s) finalizado(s).");
    }

    public function applyMessageTemplate(int $templateId): void
    {
        $ticket = $this->ticket();
        $template = $this->messageTemplateForUse($ticket, $templateId);

        if ($template->channel === TicketMessageTemplate::CHANNEL_INTERNAL) {
            $this->authorize('commentInternally', $ticket);
            $this->internalMessage = $this->textWithTemplate($this->internalMessage, $template->body);

            return;
        }

        $this->authorize('comment', $ticket);
        $this->message = $this->textWithTemplate($this->message, $template->body);
    }

    public function openPersonalTemplateForm(string $channel = TicketMessageTemplate::CHANNEL_PUBLIC): void
    {
        $ticket = $this->ticket();
        $this->authorize('viewInternalUpdates', $ticket);

        $channel = in_array($channel, TicketMessageTemplate::CHANNELS, true)
            ? $channel
            : TicketMessageTemplate::CHANNEL_PUBLIC;

        $this->resetValidation();
        $this->editingPersonalTemplateId = null;
        $this->personalTemplateForm = [
            'channel' => $channel,
            'name' => '',
            'body' => $channel === TicketMessageTemplate::CHANNEL_INTERNAL ? $this->internalMessage : $this->message,
        ];
        $this->showPersonalTemplateForm = true;
    }

    public function startEditingPersonalTemplate(int $templateId): void
    {
        $template = $this->personalTemplateForCurrentUser($templateId);

        $this->resetValidation();
        $this->editingPersonalTemplateId = $template->id;
        $this->personalTemplateForm = [
            'channel' => $template->channel,
            'name' => $template->name,
            'body' => $template->body,
        ];
        $this->showPersonalTemplateForm = true;
    }

    public function cancelPersonalTemplateForm(): void
    {
        $this->resetValidation();
        $this->editingPersonalTemplateId = null;
        $this->showPersonalTemplateForm = false;
        $this->personalTemplateForm = $this->emptyPersonalTemplateForm();
    }

    public function savePersonalTemplate(): void
    {
        $ticket = $this->ticket();
        $this->authorize('viewInternalUpdates', $ticket);

        $validated = $this->validate([
            'personalTemplateForm.channel' => ['required', Rule::in(TicketMessageTemplate::CHANNELS)],
            'personalTemplateForm.name' => ['required', 'string', 'max:120'],
            'personalTemplateForm.body' => ['required', 'string', 'max:4000'],
        ]);

        $template = $this->editingPersonalTemplateId
            ? $this->personalTemplateForCurrentUser($this->editingPersonalTemplateId)
            : new TicketMessageTemplate;

        $template->fill([
            'ticket_board_id' => $ticket->ticket_board_id,
            'user_id' => auth()->id(),
            'channel' => $validated['personalTemplateForm']['channel'],
            'name' => trim($validated['personalTemplateForm']['name']),
            'body' => trim($validated['personalTemplateForm']['body']),
            'is_active' => true,
            'sort_order' => $template->exists
                ? $template->sort_order
                : (((int) TicketMessageTemplate::query()
                    ->where('ticket_board_id', $ticket->ticket_board_id)
                    ->where('user_id', auth()->id())
                    ->max('sort_order')) + 1),
        ]);
        $template->save();

        $this->cancelPersonalTemplateForm();
        session()->flash('status', 'Template pessoal salvo com sucesso.');
    }

    public function deletePersonalTemplate(int $templateId): void
    {
        $template = $this->personalTemplateForCurrentUser($templateId);
        $template->delete();

        if ($this->editingPersonalTemplateId === $templateId) {
            $this->cancelPersonalTemplateForm();
        }

        session()->flash('status', 'Template pessoal removido com sucesso.');
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
        $fields = $board?->fields->where('is_active', true)->values() ?? collect();
        $assignees = $board ? $this->boardAssignees($board) : collect();
        $canRate = auth()->user()->can('rate', $ticket);
        $canComment = auth()->user()->can('comment', $ticket);
        $canViewInternalUpdates = auth()->user()->can('viewInternalUpdates', $ticket);
        $canCommentInternally = auth()->user()->can('commentInternally', $ticket);
        $canViewTimeTracking = auth()->user()->can('viewTimeTracking', $ticket);
        $canViewOperationalHistory = $canViewTimeTracking;
        $canTrackTime = auth()->user()->can('trackTime', $ticket);
        $canCloseOwn = auth()->user()->can('closeOwn', $ticket);
        $canReopenOwn = auth()->user()->can('reopenOwn', $ticket);
        $canManageMajorIncident = $canViewOperationalHistory && auth()->user()->can('update', $ticket);
        $canManageSubelements = $canViewOperationalHistory && auth()->user()->can('update', $ticket) && ! $ticket->isSubelement();
        $subelements = $canManageSubelements ? $ticket->subTickets->values() : collect();
        $incidentChildren = $canManageMajorIncident && $ticket->is_major_incident
            ? $ticket->incidentChildren->values()
            : collect();
        $incidentParent = $canManageMajorIncident ? $ticket->majorIncident : null;
        $incidentSuggestions = ($canManageMajorIncident && ! $ticket->major_incident_ticket_id)
            ? app(MajorIncidentService::class)->suggestions(auth()->user(), $ticket)
            : collect();
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
        $messages = $ticket->messages->sortBy('created_at')->values();
        $publicMessages = $messages
            ->reject(fn ($ticketMessage) => $ticketMessage->is_internal)
            ->values();
        $internalMessages = $canViewInternalUpdates
            ? $messages->filter(fn ($ticketMessage) => $ticketMessage->is_internal)->values()
            : collect();
        $visibleAttachments = $ticket->attachments
            ->filter(fn ($attachment) => ! ($attachment->message?->is_internal && ! $canViewInternalUpdates))
            ->values();
        $internalAudienceUsers = $canViewInternalUpdates
            ? $assignees->values()
            : collect();
        $internalMentionableUsers = $canViewInternalUpdates
            ? $internalAudienceUsers->reject(fn (User $user) => $user->id === auth()->id())->values()
            : collect();
        $messageTemplates = $canViewInternalUpdates
            ? TicketMessageTemplate::query()
                ->where('ticket_board_id', $ticket->ticket_board_id)
                ->active()
                ->where(function ($query): void {
                    $query
                        ->whereNull('user_id')
                        ->orWhere('user_id', auth()->id());
                })
                ->orderByRaw('case when user_id is null then 0 else 1 end')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
            : collect();
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

        $this->syncIncidentSelections($ticket, $incidentChildren, $canManageMajorIncident);

        return view('livewire.tickets.show-page', [
            'ticket' => $ticket,
            'messages' => $publicMessages,
            'internalMessages' => $internalMessages,
            'visibleAttachments' => $visibleAttachments,
            'board' => $board,
            'fields' => $fields,
            'requesterFields' => $this->requesterVisibleFields($ticket, $fields),
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
            'canViewInternalUpdates' => $canViewInternalUpdates,
            'canCommentInternally' => $canCommentInternally,
            'canUseMessageTemplates' => $canViewInternalUpdates,
            'publicMessageTemplates' => $messageTemplates->where('channel', TicketMessageTemplate::CHANNEL_PUBLIC)->values(),
            'internalMessageTemplates' => $messageTemplates->where('channel', TicketMessageTemplate::CHANNEL_INTERNAL)->values(),
            'personalMessageTemplates' => $messageTemplates->where('user_id', auth()->id())->values(),
            'internalAudienceUsers' => $internalAudienceUsers,
            'internalMentionableUsers' => $internalMentionableUsers,
            'canCloseOwn' => $canCloseOwn,
            'canReopenOwn' => $canReopenOwn,
            'canManageMajorIncident' => $canManageMajorIncident,
            'canManageSubelements' => $canManageSubelements,
            'subelements' => $subelements,
            'incidentChildren' => $incidentChildren,
            'incidentParent' => $incidentParent,
            'incidentSuggestions' => $incidentSuggestions,
            'canViewOperationalHistory' => $canViewOperationalHistory,
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
            'title' => $ticket->publicReference().' - '.$ticket->title,
            'subtitle' => $canViewOperationalHistory
                ? 'Detalhes, historico e conversa do chamado.'
                : 'Detalhes e conversa do chamado.',
            'headerVariant' => 'none',
        ]);
    }

    public function formatDuration(int $totalSeconds): string
    {
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function fieldValue(Ticket $ticket, TicketField $field): mixed
    {
        return $ticket->fieldValues->firstWhere('ticket_field_id', $field->id)?->primitive_value;
    }

    public function fieldOption(TicketField $field, mixed $value): ?TicketFieldOption
    {
        return $field->options->first(
            fn (TicketFieldOption $option) => (string) $option->value === (string) $value
        );
    }

    public function priorityColor(?TicketPriority $priority): string
    {
        return match ($priority) {
            TicketPriority::LOW => '#22c55e',
            TicketPriority::MEDIUM => '#f59e0b',
            TicketPriority::HIGH => '#8b5cf6',
            TicketPriority::URGENT => '#f97316',
            default => '#94a3b8',
        };
    }

    public function slaMeta(Ticket $ticket): array
    {
        $state = $ticket->overallSlaState();

        return [
            'state' => $state,
            'color' => match ($state) {
                'breached' => '#ef4444',
                'warning' => '#f59e0b',
                default => '#22c55e',
            },
            'label' => match ($state) {
                'breached' => 'Estourado',
                'warning' => 'A vencer',
                default => 'Em dia',
            },
        ];
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

    private function messageTemplateForUse(Ticket $ticket, int $templateId): TicketMessageTemplate
    {
        $this->authorize('viewInternalUpdates', $ticket);

        return TicketMessageTemplate::query()
            ->whereKey($templateId)
            ->where('ticket_board_id', $ticket->ticket_board_id)
            ->active()
            ->where(function ($query): void {
                $query
                    ->whereNull('user_id')
                    ->orWhere('user_id', auth()->id());
            })
            ->firstOrFail();
    }

    private function personalTemplateForCurrentUser(int $templateId): TicketMessageTemplate
    {
        $ticket = $this->ticket();
        $this->authorize('viewInternalUpdates', $ticket);

        return TicketMessageTemplate::query()
            ->whereKey($templateId)
            ->where('ticket_board_id', $ticket->ticket_board_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    private function textWithTemplate(string $currentText, string $templateText): string
    {
        $currentText = trim($currentText);
        $templateText = trim($templateText);

        if ($currentText === '') {
            return $templateText;
        }

        return $currentText."\n\n".$templateText;
    }

    private function emptyPersonalTemplateForm(): array
    {
        return [
            'channel' => TicketMessageTemplate::CHANNEL_PUBLIC,
            'name' => '',
            'body' => '',
        ];
    }

    private function ticket(): Ticket
    {
        $ticket = Ticket::query()
            ->visibleTo(auth()->user())
            ->with([
                'sector.company',
                'requester',
                'assignee',
                'parentTicket',
                'group',
                'status',
                'catalogItem.form.fields.options',
                'fieldValues.field.options',
                'messages.user',
                'messages.attachments.uploader',
                'attachments.message',
                'attachments.uploader',
                'activityLogs.causer',
                'rating.user',
                'generatedKnowledgeBaseArticle',
                'knowledgeBaseUsages',
                'majorIncident.requester',
                'majorIncident.group',
                'majorIncident.status',
                'incidentChildren.requester',
                'incidentChildren.assignee',
                'incidentChildren.group',
                'incidentChildren.status',
                'subTickets.requester',
                'subTickets.assignee',
                'subTickets.group',
                'subTickets.status',
                'subTickets.catalogItem',
                'subTickets.fieldValues.field.options',
            ])
            ->withCount([
                'subTickets',
                'subTickets as open_sub_tickets_count' => fn ($query) => $query->open(),
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

    private function subelement(int $subelementId): Ticket
    {
        return Ticket::query()
            ->where('parent_ticket_id', $this->ticketId)
            ->findOrFail($subelementId);
    }

    private function boardAssignees($board)
    {
        return User::query()
            ->where('is_active', true)
            ->withSectorAccess($board->sector_id, ['sector_admin', 'technician'])
            ->orderBy('name')
            ->get();
    }

    private function normalizeInternalMentionedUserIds(Ticket $ticket, array $mentionedUserIds): array
    {
        $allowedIds = User::query()
            ->where('is_active', true)
            ->withSectorAccess($ticket->sector_id, ['sector_admin', 'technician'])
            ->pluck('id');

        return collect($mentionedUserIds)
            ->map(fn (mixed $userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0 && $userId !== auth()->id())
            ->intersect($allowedIds)
            ->unique()
            ->values()
            ->all();
    }

    private function requesterVisibleFields(Ticket $ticket, Collection $fields): Collection
    {
        $formFields = $ticket->catalogItem?->form?->fields;
        $candidateFields = $formFields instanceof Collection
            ? $formFields
                ->where('is_active', true)
                ->sortBy(fn (TicketField $field) => $field->pivot?->sort_order ?? $field->sort_order)
                ->values()
            : $fields;

        return $candidateFields
            ->filter(function (TicketField $field) use ($ticket) {
                $value = $ticket->fieldValues->firstWhere('ticket_field_id', $field->id)?->primitive_value;

                if ($field->type->value === 'checkbox') {
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }

                return filled($value);
            })
            ->values();
    }

    private function syncIncidentSelections(Ticket $ticket, Collection $incidentChildren, bool $canManageMajorIncident): void
    {
        if (! $canManageMajorIncident || ! $ticket->is_major_incident) {
            $this->incidentSelectionSignature = '';

            return;
        }

        $childIds = $incidentChildren
            ->reject(fn (Ticket $child) => $child->isClosed())
            ->pluck('id')
            ->map(fn (int $childId) => (string) $childId)
            ->values()
            ->all();
        $signature = $ticket->id.':'.implode(',', $childIds);

        if ($this->incidentSelectionSignature === $signature) {
            return;
        }

        $this->incidentSelectedChildIds = $childIds;
        $this->incidentSelectionSignature = $signature;
    }

    private function resetIncidentSelections(): void
    {
        $this->incidentSelectedChildIds = [];
        $this->incidentSuggestedTicketIds = [];
        $this->incidentSelectionSignature = '';
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
