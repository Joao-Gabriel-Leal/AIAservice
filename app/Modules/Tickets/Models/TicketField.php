<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketField extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_board_id',
        'name',
        'slug',
        'type',
        'placeholder',
        'help_text',
        'settings',
        'sort_order',
        'is_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => TicketFieldType::class,
            'settings' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(TicketBoard::class, 'ticket_board_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(TicketFieldOption::class)->orderBy('sort_order');
    }

    public function values(): HasMany
    {
        return $this->hasMany(TicketFieldValue::class);
    }

    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(TicketForm::class, 'ticket_form_fields')
            ->withPivot(['is_required', 'sort_order'])
            ->withTimestamps();
    }
}
