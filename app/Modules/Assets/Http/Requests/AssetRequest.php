<?php

namespace App\Modules\Assets\Http\Requests;

use App\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $asset = $this->route('asset');
        $isUpdate = $asset instanceof Asset;

        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'serial_number' => ['nullable', 'string', 'max:160', Rule::unique('assets', 'serial_number')->ignore($asset?->id)],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
        ];

        if (! $isUpdate) {
            $rules['status'] = ['required', Rule::enum(AssetStatus::class)];
            $rules['current_sector_id'] = ['required', 'exists:sectors,id'];
            $rules['current_room_id'] = ['required', 'exists:rooms,id'];
            $rules['current_user_id'] = ['nullable', 'exists:users,id'];
        }

        return $rules;
    }
}
