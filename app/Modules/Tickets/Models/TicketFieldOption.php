<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketFieldOption extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_field_id',
        'label',
        'value',
        'color',
        'sort_order',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(TicketField::class, 'ticket_field_id');
    }
}
