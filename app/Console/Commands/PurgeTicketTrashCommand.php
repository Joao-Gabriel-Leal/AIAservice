<?php

namespace App\Console\Commands;

use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Services\TicketWorkflowService;
use Illuminate\Console\Command;

class PurgeTicketTrashCommand extends Command
{
    protected $signature = 'tickets:trash-prune';

    protected $description = 'Remove definitivamente chamados que passaram do prazo da lixeira.';

    public function handle(): int
    {
        $cutoff = now()->subDays(TicketWorkflowService::TRASH_RETENTION_DAYS);

        $subelements = Ticket::onlyTrashed()
            ->whereNotNull('parent_ticket_id')
            ->where('deleted_at', '<', $cutoff)
            ->forceDelete();

        $tickets = Ticket::onlyTrashed()
            ->whereNull('parent_ticket_id')
            ->where('deleted_at', '<', $cutoff)
            ->whereDoesntHave('subTicketsWithTrashed', function ($query) use ($cutoff): void {
                $query
                    ->whereNull('deleted_at')
                    ->orWhere('deleted_at', '>=', $cutoff);
            })
            ->forceDelete();

        $this->info("Chamados removidos definitivamente: {$tickets}; subelementos: {$subelements}.");

        return self::SUCCESS;
    }
}
