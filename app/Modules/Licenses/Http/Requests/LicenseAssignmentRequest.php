<?php

namespace App\Modules\Licenses\Http\Requests;

use App\Enums\LicenseAssignmentStatus;
use App\Modules\Licenses\Models\License;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LicenseAssignmentRequest extends FormRequest
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
            'seat_label' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::enum(LicenseAssignmentStatus::class)],
            'assigned_at' => ['nullable', 'date'],
            'released_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', LicenseAssignmentStatus::ACTIVE->value),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $license = $this->resolvedLicense();

            if (! $license instanceof License) {
                return;
            }

            $assignedAt = $this->input('assigned_at');
            $releasedAt = $this->input('released_at');

            if ($assignedAt && $releasedAt && strtotime((string) $releasedAt) < strtotime((string) $assignedAt)) {
                $validator->errors()->add('released_at', 'A desatribuicao nao pode ser anterior a atribuicao.');
            }
        });
    }

    private function resolvedLicense(): ?License
    {
        $license = $this->route('license');

        if ($license instanceof License) {
            return $license;
        }

        return $this->route('assignment')?->license;
    }
}
