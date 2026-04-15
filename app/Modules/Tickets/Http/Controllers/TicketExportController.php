<?php

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tickets\Exports\TicketsExport;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Support\TicketIndexOptions;
use App\Modules\Tickets\Support\TicketIndexQuery;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketExportController extends Controller
{
    public function __construct(
        private readonly TicketIndexQuery $ticketIndexQuery,
        private readonly TicketIndexOptions $ticketIndexOptions,
        private readonly SpreadsheetExporter $spreadsheetExporter,
    ) {
    }

    public function __invoke(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $user = $request->user();
        $filters = $this->ticketIndexQuery->filtersFromRequest($request);
        $allowedSectorIds = $this->ticketIndexOptions->sectorOptions($user)->pluck('id');

        if ($filters['sector_id']) {
            abort_unless($allowedSectorIds->contains($filters['sector_id']), 403);
        }

        $fields = $this->ticketIndexOptions->fieldOptions($user, $filters['sector_id']);
        $export = new TicketsExport($this->ticketIndexQuery->build($user, $filters)->get(), $fields);

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }
}
