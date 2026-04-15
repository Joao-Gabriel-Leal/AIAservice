<?php

namespace App\Modules\Tickets\Notifications;

class TicketRatingRequestNotification extends BaseTicketNotification
{
    protected function actionLabel(): string
    {
        return 'Avaliar chamado';
    }
}
