<?php

namespace App\Modules\Licenses\Http\Requests;

use App\Enums\LicenseAssignmentStatus;
use App\Models\User;
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
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'assigned_email' => ['nullable', 'email:rfc', 'max:190'],
            'display_name' => ['nullable', 'string', 'max:160'],
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

            if (! $this->filled('user_id') && ! $this->filled('assigned_email')) {
                $validator->errors()->add('assigned_email', 'Informe um colaborador interno ou um email para esta atribuicao.');
            }

            if ($this->filled('user_id')) {
                $user = User::query()->find($this->integer('user_id'));

                $belongsToSector = $user
                    ? $user->sectorAccesses()->where('sector_id', $license->sector_id)->exists()
                    : false;

                if (! $belongsToSector) {
                    $validator->errors()->add('user_id', 'O colaborador selecionado precisa estar vinculado ao setor desta licenca.');
                }
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
