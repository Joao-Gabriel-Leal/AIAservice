<?php

namespace App\Modules\Assets\Http\Requests;

use App\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AssetStatus::class)],
            'current_sector_id' => ['required', 'exists:sectors,id'],
            'current_room_id' => ['required', 'exists:rooms,id'],
            'current_user_id' => ['nullable', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
