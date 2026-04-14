<?php

namespace App\Modules\Users\Http\Requests;

use App\Enums\GlobalUserRole;
use App\Enums\SectorAccessLevel;
use App\Models\User;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $isUpdate = $user instanceof User;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$isUpdate ? 'nullable' : 'required', 'confirmed', 'min:8'],
            'global_role' => ['required', Rule::enum(GlobalUserRole::class)],
            'sector_accesses' => ['nullable', 'array'],
            'sector_accesses.*' => ['nullable', Rule::enum(SectorAccessLevel::class)],
            'must_change_password' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $actor = $this->user();
            $globalRole = GlobalUserRole::tryFrom((string) $this->input('global_role'));
            $sectorAccesses = collect($this->input('sector_accesses', []))
                ->filter(fn ($value) => filled($value));

            $requestedSectorIds = $sectorAccesses
                ->keys()
                ->map(fn ($sectorId) => (int) $sectorId)
                ->filter()
                ->values();

            $existingSectorIds = Sector::query()
                ->whereIn('id', $requestedSectorIds)
                ->pluck('id')
                ->map(fn ($sectorId) => (int) $sectorId)
                ->all();

            if ($requestedSectorIds->diff($existingSectorIds)->isNotEmpty()) {
                $validator->errors()->add('sector_accesses', 'Existem setores informados que nao sao validos.');
            }

            if (! $actor->isSuperAdmin()) {
                if ($globalRole === GlobalUserRole::SUPER_ADMIN) {
                    $validator->errors()->add('global_role', 'Admin de setor nao pode criar ou promover super admins.');
                }

                $unauthorizedSectorIds = $requestedSectorIds->diff($actor->adminSectorIds());

                if ($unauthorizedSectorIds->isNotEmpty()) {
                    $validator->errors()->add('sector_accesses', 'Voce so pode gerenciar acessos dos setores que administra.');
                }

                if (! $this->route('user') && $sectorAccesses->isEmpty()) {
                    $validator->errors()->add('sector_accesses', 'Selecione ao menos um acesso setorial para o novo colaborador.');
                }
            }
        });
    }
}
