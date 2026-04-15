<?php

namespace App\Modules\KnowledgeBase\Models;

use App\Models\User;
use App\Support\DatabaseBinary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeBaseAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'knowledge_base_article_id',
        'uploaded_by_id',
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

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseArticle::class, 'knowledge_base_article_id');
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

    public function humanSize(): string
    {
        $bytes = max((int) ($this->size ?? 0), 0);

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', '.').' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }

        return $bytes.' B';
    }
}
