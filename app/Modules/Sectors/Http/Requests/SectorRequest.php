<?php

namespace App\Modules\Sectors\Http\Requests;

use App\Modules\Sectors\Support\SectorColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'sector_template_id' => [
                'nullable',
                Rule::exists('sector_templates', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:32', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && $value !== '' && ! SectorColor::isValid((string) $value)) {
                    $fail('Informe uma cor hexadecimal ou um nome CSS valido.');
                }
            }],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'return_to_company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];
    }
}
