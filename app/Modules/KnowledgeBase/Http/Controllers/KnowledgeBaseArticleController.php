<?php

namespace App\Modules\KnowledgeBase\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\KnowledgeBase\Exports\KnowledgeBaseArticlesExport;
use App\Modules\KnowledgeBase\Http\Requests\KnowledgeBaseArticleRequest;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseAttachment;
use App\Modules\KnowledgeBase\Services\KnowledgeBaseArticleSearchService;
use App\Modules\Sectors\Models\Sector;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KnowledgeBaseArticleController extends Controller
{
    public function __construct(
        private readonly KnowledgeBaseArticleSearchService $searchService,
        private readonly SpreadsheetExporter $spreadsheetExporter,
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

        return view('modules.knowledge-base.index', [
            'articles' => $this->searchService->paginateVisible($request->user(), $request->string('search')->toString()),
            'search' => $request->string('search')->toString(),
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
            'article' => $article->load(['sector.company', 'author', 'attachments.uploader']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', KnowledgeBaseArticle::class);

        return view('modules.knowledge-base.create', [
            'article' => new KnowledgeBaseArticle,
            'sectors' => $this->availableSectors(),
        ]);
    }

    public function store(KnowledgeBaseArticleRequest $request): RedirectResponse
    {
        $this->authorize('create', KnowledgeBaseArticle::class);

        $payload = $request->safe()->except([
            'attachments',
            'remove_attachments',
        ]);

        $article = KnowledgeBaseArticle::query()->create([
            ...$payload,
            'created_by' => $request->user()->id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->storeAttachments($article, $request->file('attachments', []), $request->user()->id);

        return redirect()->route('knowledge-base.manage')->with('status', 'Artigo criado com sucesso.');
    }

    public function edit(KnowledgeBaseArticle $article): View
    {
        $this->authorize('update', $article);

        return view('modules.knowledge-base.edit', [
            'article' => $article->load(['attachments.uploader', 'sector.company', 'author']),
            'sectors' => $this->availableSectors(),
        ]);
    }

    public function update(KnowledgeBaseArticleRequest $request, KnowledgeBaseArticle $article): RedirectResponse
    {
        $this->authorize('update', $article);

        $payload = $request->safe()->except([
            'attachments',
            'remove_attachments',
        ]);

        $article->update([
            ...$payload,
            'is_active' => $request->boolean('is_active', false),
        ]);

        $this->removeAttachments($article, $request->input('remove_attachments', []));
        $this->storeAttachments($article, $request->file('attachments', []), $request->user()->id);

        return redirect()->route('knowledge-base.manage')->with('status', 'Artigo atualizado com sucesso.');
    }

    public function destroy(KnowledgeBaseArticle $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $article->delete();

        return redirect()->route('knowledge-base.manage')->with('status', 'Artigo removido com sucesso.');
    }

    private function availableSectors(): Collection
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (! auth()->user()->isSuperAdmin()) {
            $query->whereIn('id', auth()->user()->adminSectorIds());
        }

        return $query->get();
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
}
