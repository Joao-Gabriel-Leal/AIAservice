<?php

namespace App\Modules\KnowledgeBase\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseAttachment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KnowledgeBaseAttachmentController extends Controller
{
    public function show(KnowledgeBaseAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->article);

        $content = $attachment->binaryContent();

        abort_if($content === null, 404);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) ($attachment->size ?? strlen($content)),
        ]);
    }
}
