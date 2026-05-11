<?php

namespace App\Modules\KnowledgeBase\Http\Controllers;

use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use App\Http\Controllers\Controller;
use App\Modules\KnowledgeBase\Exports\KnowledgeBaseArticlesExport;
use App\Modules\KnowledgeBase\Http\Requests\KnowledgeBaseArticleRequest;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticleFeedback;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticleTicketUsage;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseAttachment;
use App\Modules\KnowledgeBase\Services\KnowledgeBaseArticleSearchService;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Services\ActivityLogService;
use App\Modules\Shared\Support\CurrentCompanyContext;
use App\Modules\Tickets\Models\Ticket;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KnowledgeBaseArticleController extends Controller
{
    public function __construct(
        private readonly KnowledgeBaseArticleSearchService $searchService,
        private readonly SpreadsheetExporter $spreadsheetExporter,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function manage(Request $request): View
    {
        $this->authorize('create', KnowledgeBaseArticle::class);

        return view('modules.knowledge-base.manage-index', [
            'articles' => $this->searchService->paginateAdmin($request->user(), $request->string('search')->toString()),
            'search' => $request->string('search')->toString(),
        ]);
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', KnowledgeBaseArticle::class);
        $search = $request->string('search')->toString();

        return view('modules.knowledge-base.index', [
            'articles' => $this->searchService->paginateVisible($request->user(), $search),
            'search' => $search,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', KnowledgeBaseArticle::class);

        $export = new KnowledgeBaseArticlesExport(
            $this->searchService->visibleQuery($request->user(), $request->string('search')->toString())->get(),
        );

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }

    public function exportManage(Request $request): BinaryFileResponse
    {
        $this->authorize('create', KnowledgeBaseArticle::class);

        $export = new KnowledgeBaseArticlesExport(
            $this->searchService->adminQuery($request->user(), $request->string('search')->toString())->get(),
            true,
        );

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }

    public function show(KnowledgeBaseArticle $article): View
    {
        $this->authorize('view', $article);

        return view('modules.knowledge-base.show', [
            'article' => $article->load([
                'sector.company',
                'author',
                'attachments.uploader',
                'sourceTicket.requester',
                'sourceTicket.assignee',
            ])->loadCount([
                'feedback as helpful_feedback_count' => fn ($query) => $query->where('is_helpful', true),
                'feedback as not_helpful_feedback_count' => fn ($query) => $query->where('is_helpful', false),
                'ticketUsages',
            ]),
            'userFeedback' => $article->feedback()->where('user_id', auth()->id())->first(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', KnowledgeBaseArticle::class);

        return view('modules.knowledge-base.create', [
            'article' => new KnowledgeBaseArticle,
            'sectors' => $this->availableSectors(),
            'sourceTicket' => null,
            'canPublish' => true,
            'formAction' => route('knowledge-base.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function createFromTicket(Ticket $ticket): View|RedirectResponse
    {
        $this->authorize('createFromTicket', [KnowledgeBaseArticle::class, $ticket]);
        abort_unless(app(CurrentCompanyContext::class)->ensureForCompany(auth()->user(), (int) $ticket->sector?->company_id), 403);

        $ticket->loadMissing(['sector.company', 'requester', 'assignee', 'messages.user', 'group']);

        if ($ticket->generatedKnowledgeBaseArticle()->exists()) {
            return redirect()
                ->route('knowledge-base.show', $ticket->generatedKnowledgeBaseArticle)
                ->with('status', 'Este chamado ja originou um artigo da base.');
        }

        $article = new KnowledgeBaseArticle([
            'sector_id' => $ticket->sector_id,
            'generated_from_ticket_id' => $ticket->id,
            'title' => $this->suggestedTitle($ticket),
            'summary' => $this->suggestedSummary($ticket),
            'content' => $this->suggestedContent($ticket),
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'editorial_status' => auth()->user()->can('create', KnowledgeBaseArticle::class)
                ? KnowledgeBaseArticleStatus::PUBLISHED
                : KnowledgeBaseArticleStatus::DRAFT,
            'is_active' => auth()->user()->can('create', KnowledgeBaseArticle::class),
        ]);

        return view('modules.knowledge-base.create', [
            'article' => $article,
            'sectors' => $this->availableSectors($ticket),
            'sourceTicket' => $ticket,
            'canPublish' => auth()->user()->can('create', KnowledgeBaseArticle::class),
            'formAction' => route('knowledge-base.from-ticket.store', $ticket),
            'formMethod' => 'POST',
        ]);
    }

    public function store(KnowledgeBaseArticleRequest $request): RedirectResponse
    {
        $this->authorize('create', KnowledgeBaseArticle::class);

        $payload = $request->safe()->except([
            'attachments',
            'cover_image',
            'remove_attachments',
            'remove_cover_image',
        ]);
        abort_unless($this->availableSectors()->pluck('id')->contains((int) $payload['sector_id']), 403);

        if ($coverImagePath = $this->storeCoverImage($request->file('cover_image'))) {
            $payload['cover_image_path'] = $coverImagePath;
        }

        $article = KnowledgeBaseArticle::query()->create([
            ...$this->normalizedPayload($payload),
            'created_by' => $request->user()->id,
        ]);

        $this->activityLogService->logChanges(
            $request->user(),
            $article,
            'knowledge_base.article.created',
            'Artigo criado.',
            [],
            $this->articleAuditSnapshot($article),
            [
                'sector_id' => $article->sector_id,
                'article_id' => $article->id,
            ],
        );

        $this->storeAttachments($article, $request->file('attachments', []), $request->user()->id);

        return redirect()->route('knowledge-base.manage')->with('status', 'Artigo criado com sucesso.');
    }

    public function storeFromTicket(KnowledgeBaseArticleRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('createFromTicket', [KnowledgeBaseArticle::class, $ticket]);
        abort_unless(app(CurrentCompanyContext::class)->ensureForCompany($request->user(), (int) $ticket->sector?->company_id), 403);

        if ($ticket->generatedKnowledgeBaseArticle()->exists()) {
            return redirect()
                ->route('tickets.show', $ticket)
                ->with('status', 'Este chamado ja possui um artigo vinculado.');
        }

        $payload = $request->safe()->except([
            'attachments',
            'cover_image',
            'remove_attachments',
            'remove_cover_image',
        ]);
        abort_unless($this->availableSectors($ticket)->pluck('id')->contains((int) ($payload['sector_id'] ?? $ticket->sector_id)), 403);

        $canPublish = $request->user()->can('create', KnowledgeBaseArticle::class);

        if ($coverImagePath = $this->storeCoverImage($request->file('cover_image'))) {
            $payload['cover_image_path'] = $coverImagePath;
        }

        $article = KnowledgeBaseArticle::query()->create([
            ...$this->normalizedPayload($payload, $ticket, $canPublish),
            'created_by' => $request->user()->id,
        ]);

        $this->activityLogService->logChanges(
            $request->user(),
            $article,
            'knowledge_base.article.created_from_ticket',
            'Artigo criado a partir de chamado.',
            [],
            $this->articleAuditSnapshot($article),
            [
                'sector_id' => $article->sector_id,
                'article_id' => $article->id,
                'ticket_id' => $ticket->id,
            ],
        );

        $this->storeAttachments($article, $request->file('attachments', []), $request->user()->id);
        $this->recordTicketUsage($article, $ticket, $request->user()->id);

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', $canPublish
                ? 'Artigo criado a partir do chamado com sucesso.'
                : 'Rascunho criado a partir do chamado e enviado para revisao.');
    }

    public function edit(KnowledgeBaseArticle $article): View
    {
        $this->authorize('update', $article);

        return view('modules.knowledge-base.edit', [
            'article' => $article->load(['attachments.uploader', 'sector.company', 'author']),
            'sectors' => $this->availableSectors(),
            'sourceTicket' => $article->sourceTicket,
            'canPublish' => true,
            'formAction' => route('knowledge-base.update', $article),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(KnowledgeBaseArticleRequest $request, KnowledgeBaseArticle $article): RedirectResponse
    {
        $this->authorize('update', $article);

        $payload = $request->safe()->except([
            'attachments',
            'cover_image',
            'remove_attachments',
            'remove_cover_image',
        ]);
        abort_unless($this->availableSectors()->pluck('id')->contains((int) $payload['sector_id']), 403);

        $coverImagePathToDelete = null;
        $before = $this->articleAuditSnapshot($article);

        if ($coverImagePath = $this->storeCoverImage($request->file('cover_image'))) {
            $coverImagePathToDelete = $article->cover_image_path;
            $payload['cover_image_path'] = $coverImagePath;
        } elseif ($request->boolean('remove_cover_image')) {
            $coverImagePathToDelete = $article->cover_image_path;
            $payload['cover_image_path'] = null;
        }

        $article->update([
            ...$this->normalizedPayload($payload, null, true, false),
        ]);

        $this->activityLogService->logChanges(
            $request->user(),
            $article,
            'knowledge_base.article.updated',
            'Artigo atualizado.',
            $before,
            $this->articleAuditSnapshot($article->fresh()),
            [
                'sector_id' => $article->sector_id,
                'article_id' => $article->id,
            ],
        );

        $this->deleteCoverImagePath($coverImagePathToDelete);

        $this->removeAttachments($article, $request->input('remove_attachments', []));
        $this->storeAttachments($article, $request->file('attachments', []), $request->user()->id);

        return redirect()->route('knowledge-base.manage')->with('status', 'Artigo atualizado com sucesso.');
    }

    public function destroy(KnowledgeBaseArticle $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $before = $this->articleAuditSnapshot($article);

        $this->activityLogService->log(
            auth()->user(),
            $article,
            'knowledge_base.article.deleted',
            'Artigo removido.',
            [
                'sector_id' => $article->sector_id,
                'article_id' => $article->id,
                'before' => $before,
                'changes' => $this->activityLogService->changes($before, array_fill_keys(array_keys($before), null)),
            ],
        );

        $article->delete();

        return redirect()->route('knowledge-base.manage')->with('status', 'Artigo removido com sucesso.');
    }

    public function submitFeedback(Request $request, KnowledgeBaseArticle $article): RedirectResponse
    {
        $this->authorize('view', $article);

        $validated = $request->validate([
            'is_helpful' => ['required', 'boolean'],
        ]);

        KnowledgeBaseArticleFeedback::query()->updateOrCreate(
            [
                'knowledge_base_article_id' => $article->id,
                'user_id' => $request->user()->id,
            ],
            [
                'is_helpful' => (bool) $validated['is_helpful'],
            ],
        );

        return back()->with('status', 'Feedback registrado com sucesso.');
    }

    public function markUsage(Request $request, KnowledgeBaseArticle $article, Ticket $ticket): RedirectResponse
    {
        $this->authorize('view', $article);
        $this->authorize('update', $ticket);

        abort_unless($ticket->isClosed(), 422, 'O chamado precisa estar encerrado para vincular um artigo.');

        $this->recordTicketUsage($article, $ticket, $request->user()->id);

        return back()->with('status', 'Artigo vinculado ao chamado com sucesso.');
    }

    private function availableSectors(?Ticket $ticket = null): Collection
    {
        $query = Sector::query()->with('company')->orderBy('name');
        $query->where('company_id', app(CurrentCompanyContext::class)->currentCompanyId(auth()->user()) ?: 0);

        if ($ticket && ! auth()->user()->can('create', KnowledgeBaseArticle::class)) {
            $query->whereKey($ticket->sector_id);
        } elseif (! auth()->user()->isSuperAdmin()) {
            $query->whereIn('id', auth()->user()->adminSectorIds());
        }

        return $query->get();
    }

    private function normalizedPayload(
        array $payload,
        ?Ticket $sourceTicket = null,
        bool $canPublish = true,
        bool $defaultActive = true,
    ): array {
        $editorialStatus = $payload['editorial_status'] ?? null;

        if (! $canPublish) {
            $editorialStatus = KnowledgeBaseArticleStatus::DRAFT->value;
        }

        return [
            ...$payload,
            'sector_id' => $sourceTicket?->sector_id ?? $payload['sector_id'],
            'generated_from_ticket_id' => $sourceTicket?->id ?? ($payload['generated_from_ticket_id'] ?? null),
            'editorial_status' => $editorialStatus ?: KnowledgeBaseArticleStatus::PUBLISHED->value,
            'is_active' => $canPublish
                ? (bool) ($payload['is_active'] ?? $defaultActive)
                : false,
        ];
    }

    private function suggestedTitle(Ticket $ticket): string
    {
        return Str::limit('Solucao: '.$ticket->title, 160, '');
    }

    private function suggestedSummary(Ticket $ticket): string
    {
        $latestMessage = $ticket->messages
            ->sortByDesc('created_at')
            ->firstWhere('is_system', false);

        return Str::limit(
            trim((string) ($latestMessage?->message ?: $ticket->description ?: 'Artigo gerado a partir da resolucao do chamado.')),
            500,
            '...'
        );
    }

    private function suggestedContent(Ticket $ticket): string
    {
        $messages = $ticket->messages
            ->where('is_system', false)
            ->sortBy('created_at')
            ->take(-3)
            ->values();

        $steps = $messages->map(function ($message, int $index) {
            return ($index + 1).'. '.$message->message;
        })->implode("\n");

        return trim(implode("\n\n", [
            '## Contexto do problema',
            $ticket->description ?: 'Descrever o contexto inicial do chamado.',
            '## Causa ou diagnostico',
            'Registrar aqui a causa identificada ou a hipotese validada durante o atendimento.',
            '## Passos da solucao',
            $steps !== '' ? $steps : '1. Descrever os passos executados para resolver o chamado.',
            '## Observacoes finais',
            $ticket->fullReference()
                .($ticket->resolved_at ? ' encerrado em '.$ticket->resolved_at->format('d/m/Y H:i').'.' : '.'),
        ]));
    }

    private function recordTicketUsage(KnowledgeBaseArticle $article, Ticket $ticket, int $userId): void
    {
        KnowledgeBaseArticleTicketUsage::query()->firstOrCreate([
            'knowledge_base_article_id' => $article->id,
            'ticket_id' => $ticket->id,
        ], [
            'used_by_id' => $userId,
        ]);
    }

    private function storeAttachments(KnowledgeBaseArticle $article, array $uploadedFiles, int $userId): void
    {
        collect($uploadedFiles)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->each(function (UploadedFile $file) use ($article, $userId) {
                $content = file_get_contents($file->getRealPath());

                if ($content === false) {
                    return;
                }

                $attachment = new KnowledgeBaseAttachment([
                    'uploaded_by_id' => $userId,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => strlen($content),
                ]);

                $attachment->content = $attachment->encodeContentForStorage($content);

                $article->attachments()->save($attachment);
            });
    }

    private function storeCoverImage(?UploadedFile $file): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $storedPath = $file->store('knowledge-base-covers', 'public');

        return is_string($storedPath) ? $storedPath : null;
    }

    private function deleteCoverImagePath(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function removeAttachments(KnowledgeBaseArticle $article, array $attachmentIds): void
    {
        $normalizedIds = collect($attachmentIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($normalizedIds->isEmpty()) {
            return;
        }

        $article->attachments()->whereIn('id', $normalizedIds)->delete();
    }

    private function articleAuditSnapshot(KnowledgeBaseArticle $article): array
    {
        return $this->activityLogService->snapshot($article, [
            'sector_id',
            'created_by',
            'generated_from_ticket_id',
            'title',
            'summary',
            'cover_image_path',
            'content',
            'visibility',
            'editorial_status',
            'is_active',
        ]);
    }
}
