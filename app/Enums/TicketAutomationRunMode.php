<?php

namespace App\Enums;

enum TicketAutomationRunMode: string
{
    case SYNC = 'sync';
    case ASYNC = 'async';
    case SCHEDULED = 'scheduled';
}
