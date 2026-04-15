<?php

namespace App\Modules\Companies\Models;

use App\Modules\Sectors\Models\Sector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'document',
        'email',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected function document(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => self::formatDocument($value),
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => self::formatPhone($value),
        );
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }

    private static function formatDocument(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        }

        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits);
        }

        return $value;
    }

    private static function formatPhone(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return match (strlen($digits)) {
            10 => preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits),
            11 => preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits),
            default => $value,
        };
    }
}
