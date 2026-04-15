<?php

namespace App\Modules\Tickets\Models;

use App\Models\User;
use App\Support\DatabaseBinary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'uploaded_by_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'content',
    ];

    protected $hidden = [
        'content',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function binaryContent(): ?string
    {
        return DatabaseBinary::decode($this->content);
    }

    public function encodeContentForStorage(string $content): string
    {
        return DatabaseBinary::encode($content, $this->getConnection()->getDriverName()) ?? $content;
    }
}
