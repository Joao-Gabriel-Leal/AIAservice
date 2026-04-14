<?php

namespace Database\Seeders;

use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Services\TicketSlaService;
use Illuminate\Database\Seeder;

class TicketSlaSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(TicketSlaService::class);

        TicketBoard::query()->each(fn (TicketBoard $board) => $service->ensurePolicy($board));
    }
}
