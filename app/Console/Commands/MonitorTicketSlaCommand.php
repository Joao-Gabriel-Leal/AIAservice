<?php

namespace App\Console\Commands;

use App\Modules\Tickets\Services\TicketSlaService;
use Illuminate\Console\Command;

class MonitorTicketSlaCommand extends Command
{
    protected $signature = 'tickets:sla-monitor';

    protected $description = 'Verifica prazos de SLA dos chamados e dispara alertas.';

    public function handle(TicketSlaService $ticketSlaService): int
    {
        $ticketSlaService->monitorOpenTickets();

        $this->info('Monitoramento de SLA executado com sucesso.');

        return self::SUCCESS;
    }
}
