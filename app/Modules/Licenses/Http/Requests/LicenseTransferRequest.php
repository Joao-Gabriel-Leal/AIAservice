<?php

namespace App\Modules\Licenses\Http\Requests;

use App\Modules\Licenses\Models\License;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LicenseTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'external_reference' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $license = $this->route('license');

            if (! $license instanceof License) {
                return;
            }
        });
    }
}
