<?php

namespace App\Modules\Tickets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessageTemplate extends Model
{
    public const CHANNEL_PUBLIC = 'public';

    public const CHANNEL_INTERNAL = 'internal';

    public const CHANNELS = [
        self::CHANNEL_PUBLIC,
        self::CHANNEL_INTERNAL,
    ];

    protected $fillable = [
        'ticket_board_id',
        'user_id',
        'channel',
        'name',
        'body',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(TicketBoard::class, 'ticket_board_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeShared(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    public function scopePersonalFor(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function channelLabel(string $channel): string
    {
        return match ($channel) {
            self::CHANNEL_INTERNAL => 'Atualizacao interna',
            default => 'Chat do chamado',
        };
    }

    public function isPersonal(): bool
    {
        return $this->user_id !== null;
    }
}
