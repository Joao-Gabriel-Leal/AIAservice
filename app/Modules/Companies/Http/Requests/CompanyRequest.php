<?php

namespace App\Modules\Companies\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'document' => $this->normalizeDigits($this->input('document')),
            'phone' => $this->normalizeDigits($this->input('phone')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'document' => ['nullable', 'string', 'regex:/^(\d{11}|\d{14})$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.regex' => 'Informe um CPF ou CNPJ valido.',
            'phone.regex' => 'Informe um telefone valido com DDD.',
        ];
    }

    private function normalizeDigits(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? $digits : null;
    }
}
