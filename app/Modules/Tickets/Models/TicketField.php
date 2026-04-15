<?php

namespace App\Modules\Tickets\Models;

use App\Enums\TicketFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
        'show_on_board',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => TicketFieldType::class,
            'settings' => 'array',
            'is_required' => 'boolean',
            'show_on_board' => 'boolean',
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

    public function inferredMaskType(): ?string
    {
        $metadata = Str::lower(Str::ascii(implode(' ', array_filter([
            $this->name,
            $this->slug,
            $this->placeholder,
            $this->help_text,
        ]))));

        return match (true) {
            Str::contains($metadata, ['telefone', 'celular', 'whatsapp', 'fone', 'contato']) => 'phone',
            Str::contains($metadata, ['cpf/cnpj', 'cpf cnpj', 'cpf', 'cnpj', 'documento']) => 'cpf-cnpj',
            Str::contains($metadata, ['cep', 'codigo postal', 'endereco']) => 'cep',
            Str::contains($metadata, ['placa', 'veiculo']) => 'plate',
            default => null,
        };
    }

    public function normalizeMaskedValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match ($this->inferredMaskType()) {
            'phone' => $this->digitsOnly($value),
            'cpf-cnpj' => $this->digitsOnly($value),
            'cep' => $this->digitsOnly($value),
            'plate' => Str::upper(preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? ''),
            default => $value,
        };
    }

    public function formatMaskedValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return match ($this->inferredMaskType()) {
            'phone' => $this->formatPhone($value),
            'cpf-cnpj' => $this->formatCpfCnpj($value),
            'cep' => $this->formatCep($value),
            'plate' => $this->formatPlate($value),
            default => $value,
        };
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function formatPhone(string $value): string
    {
        $digits = $this->digitsOnly($value);

        return match (strlen($digits)) {
            10 => preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits),
            11 => preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits),
            default => $value,
        };
    }

    private function formatCpfCnpj(string $value): string
    {
        $digits = $this->digitsOnly($value);

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        }

        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits);
        }

        return $value;
    }

    private function formatCep(string $value): string
    {
        $digits = $this->digitsOnly($value);

        if (strlen($digits) !== 8) {
            return $value;
        }

        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits);
    }

    private function formatPlate(string $value): string
    {
        $normalized = Str::upper(preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? '');

        if (strlen($normalized) !== 7) {
            return $normalized;
        }

        return substr($normalized, 0, 3).'-'.substr($normalized, 3);
    }
}
