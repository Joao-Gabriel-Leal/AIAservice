<?php

namespace App\Modules\Tickets\Events;

use App\Modules\Tickets\Models\TicketMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TicketMessage $message)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tickets.{$this->message->ticket_id}")];
    }

    public function broadcastAs(): string
    {
        return 'ticket.message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'ticket_id' => $this->message->ticket_id,
            'message' => $this->message->message,
            'created_at' => $this->message->created_at?->toIso8601String(),
            'user' => [
                'id' => $this->message->user?->id,
                'name' => $this->message->user?->name,
                'initials' => $this->message->user?->initials(),
            ],
        ];
    }
}
