<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'ticket_field_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(TicketField::class, 'ticket_field_id');
    }

    public function getPrimitiveValueAttribute(): mixed
    {
        $value = $this->value['value'] ?? null;

        if ($this->relationLoaded('field') && $this->field) {
            return $this->field->formatMaskedValue($value);
        }

        return $value;
    }

    public function storePrimitiveValue(mixed $value): void
    {
        $this->value = ['value' => $value];
    }
}
