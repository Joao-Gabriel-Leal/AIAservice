<?php

namespace App\Console\Commands;

use App\Enums\TicketAutomationTrigger;
use App\Jobs\RunTicketInactiveAutomationRuleJob;
use App\Modules\Tickets\Models\TicketAutomationRule;
use Illuminate\Console\Command;

class RunTicketInactiveAutomationsCommand extends Command
{
    protected $signature = 'tickets:run-inactive-automations';

    protected $description = 'Despacha as automacoes de inatividade de chamados.';

    public function handle(): int
    {
        TicketAutomationRule::query()
            ->where('is_active', true)
            ->where('trigger', TicketAutomationTrigger::TICKET_INACTIVE->value)
            ->orderBy('ticket_board_id')
            ->orderBy('sort_order')
            ->chunkById(100, function ($rules) {
                foreach ($rules as $rule) {
                    RunTicketInactiveAutomationRuleJob::dispatch($rule->id);
                }
            });

        $this->info('Automacoes de inatividade despachadas com sucesso.');

        return self::SUCCESS;
    }
}
