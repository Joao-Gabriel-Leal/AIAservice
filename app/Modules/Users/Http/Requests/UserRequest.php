<?php

namespace App\Modules\Users\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
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
            'role' => ['required', Rule::enum(UserRole::class)],
            'sector_id' => ['nullable', 'exists:sectors,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'must_change_password' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $role = UserRole::tryFrom((string) $this->input('role'));
            $sectorId = $this->input('sector_id');
            $roomId = $this->input('room_id');

            if ($role && $role !== UserRole::SUPER_ADMIN) {
                if (! $sectorId) {
                    $validator->errors()->add('sector_id', 'Selecione um setor.');
                }

                if (! $roomId) {
                    $validator->errors()->add('room_id', 'Selecione uma sala.');
                }
            }

            if ($this->user()->isSectorAdmin() && $role === UserRole::SUPER_ADMIN) {
                $validator->errors()->add('role', 'Admin de setor não pode criar super admins.');
            }
        });
    }
}
