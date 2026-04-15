<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_form_id',
        'ticket_field_id',
        'is_required',
        'sort_order',
        'visibility_parent_field_id',
        'visibility_operator',
        'visibility_expected_value',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'visibility_parent_field_id' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(TicketForm::class, 'ticket_form_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(TicketField::class, 'ticket_field_id');
    }

    public function visibilityParentField(): BelongsTo
    {
        return $this->belongsTo(TicketField::class, 'visibility_parent_field_id');
    }
}
