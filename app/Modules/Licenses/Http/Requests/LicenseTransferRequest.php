<?php

namespace App\Modules\Licenses\Http\Requests;

use App\Models\User;
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
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'assigned_email' => ['nullable', 'email:rfc', 'max:190'],
            'display_name' => ['nullable', 'string', 'max:160'],
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

            if (! $this->filled('user_id') && ! $this->filled('assigned_email')) {
                $validator->errors()->add('assigned_email', 'Informe o novo colaborador ou email para a transferencia.');
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
        });
    }
}
