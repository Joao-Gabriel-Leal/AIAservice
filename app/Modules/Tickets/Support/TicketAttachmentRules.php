<?php

namespace App\Modules\Tickets\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TicketAttachmentRules
{
    public const MAX_FILE_COUNT = 5;

    public const MAX_FILE_SIZE_KB = 10240;

    public const MAX_TOTAL_SIZE_KB = 25600;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/x-zip-compressed',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const SAFE_INLINE_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    private const SAFE_INLINE_VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
    ];

    public static function validationRules(string $field): array
    {
        return [
            $field => ['array', 'max:'.self::MAX_FILE_COUNT],
            "{$field}.*" => [
                'file',
                'max:'.self::MAX_FILE_SIZE_KB,
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
            ],
        ];
    }

    public static function validationMessages(string $field): array
    {
        return [
            "{$field}.max" => 'Envie no maximo '.self::MAX_FILE_COUNT.' arquivos por envio.',
            "{$field}.*.max" => 'Cada arquivo pode ter no maximo 10 MB.',
            "{$field}.*.mimetypes" => 'Use apenas imagens, videos e documentos permitidos.',
        ];
    }

    /**
     * @param  array<int, mixed>  $files
     * @return array<int, UploadedFile>
     */
    public static function validate(array $files, string $field = 'attachments'): array
    {
        $payload = [
            $field => static::uploadedFiles($files)->values()->all(),
        ];

        $validator = Validator::make(
            $payload,
            static::validationRules($field),
            static::validationMessages($field),
        );

        $validator->after(function ($validator) use ($payload, $field) {
            if (static::totalSizeInKilobytes($payload[$field] ?? []) > self::MAX_TOTAL_SIZE_KB) {
                $validator->errors()->add($field, 'O total dos arquivos pode ter no maximo 25 MB por envio.');
            }
        });

        $validator->validate();

        return $payload[$field] ?? [];
    }

    /**
     * @param  array<int, mixed>  $files
     */
    public static function assertWithinTotalSize(array $files, string $field = 'attachments'): void
    {
        if (static::totalSizeInKilobytes($files) > self::MAX_TOTAL_SIZE_KB) {
            throw ValidationException::withMessages([
                $field => 'O total dos arquivos pode ter no maximo 25 MB por envio.',
            ]);
        }
    }

    public static function isSafeInlineImage(?string $mimeType): bool
    {
        return in_array((string) $mimeType, self::SAFE_INLINE_IMAGE_MIME_TYPES, true);
    }

    public static function isSafeInlineVideo(?string $mimeType): bool
    {
        return in_array((string) $mimeType, self::SAFE_INLINE_VIDEO_MIME_TYPES, true);
    }

    public static function isSafeInlinePreview(?string $mimeType): bool
    {
        return static::isSafeInlineImage($mimeType) || static::isSafeInlineVideo($mimeType);
    }

    /**
     * @param  array<int, mixed>  $files
     * @return Collection<int, UploadedFile>
     */
    private static function uploadedFiles(array $files): Collection
    {
        return collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();
    }

    /**
     * @param  array<int, mixed>  $files
     */
    private static function totalSizeInKilobytes(array $files): int
    {
        $totalBytes = static::uploadedFiles($files)
            ->sum(fn (UploadedFile $file) => max((int) ($file->getSize() ?? 0), 0));

        return (int) ceil($totalBytes / 1024);
    }
}
