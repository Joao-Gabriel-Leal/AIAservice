<?php

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tickets\Models\TicketAttachment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentController extends Controller
{
    public function show(TicketAttachment $attachment): Response
    {
        $this->authorize('view', $attachment->ticket);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
