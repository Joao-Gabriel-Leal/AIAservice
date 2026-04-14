<?php

namespace App\Enums;

enum TicketAutomationExecutionStatus: string
{
    case SKIPPED = 'skipped';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
