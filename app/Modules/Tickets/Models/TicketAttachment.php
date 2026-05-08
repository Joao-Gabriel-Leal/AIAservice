<?php

namespace App\Modules\Tickets\Models;

use App\Models\User;
use App\Modules\Tickets\Support\TicketAttachmentRules;
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
        'ticket_message_id',
        'uploaded_by_id',
        'source',
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

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function isImage(): bool
    {
        return TicketAttachmentRules::isSafeInlineImage($this->mime_type);
    }

    public function isVideo(): bool
    {
        return TicketAttachmentRules::isSafeInlineVideo($this->mime_type);
    }

    public function canBePreviewedInline(): bool
    {
        return TicketAttachmentRules::isSafeInlinePreview($this->mime_type);
    }

    public function displaySize(): string
    {
        $size = (int) ($this->size ?? 0);

        if ($size >= 1024 * 1024) {
            return number_format($size / 1024 / 1024, 1).' MB';
        }

        return number_format($size / 1024, 1).' KB';
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
