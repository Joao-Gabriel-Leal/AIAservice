<?php

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tickets\Models\TicketAttachment;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function show(TicketAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->ticket);

        $content = $attachment->binaryContent();

        abort_if($content === null, 404);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) ($attachment->size ?? strlen($content)),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function inline(TicketAttachment $attachment): Response
    {
        $this->authorize('view', $attachment->ticket);
        abort_unless($attachment->canBePreviewedInline(), 404);

        $content = $attachment->binaryContent();

        abort_if($content === null, 404);

        return response($content, 200, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) ($attachment->size ?? strlen($content)),
            'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
