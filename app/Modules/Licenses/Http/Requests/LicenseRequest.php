<?php

namespace App\Modules\Licenses\Http\Requests;

use App\Enums\LicenseAssignmentStatus;
use App\Enums\LicenseBillingCycle;
use App\Enums\LicenseStatus;
use App\Modules\Licenses\Models\License;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $allowedSectorIds = $user && ! $user->isGlobalAdmin() ? $user->operationalSectorIds() : [];

        $sectorRules = ['required', 'integer', Rule::exists('sectors', 'id')];

        if ($allowedSectorIds !== []) {
            $sectorRules[] = Rule::in($allowedSectorIds);
        }

        return [
            'sector_id' => $sectorRules,
            'vendor_name' => ['required', 'string', 'max:120'],
            'product_name' => ['required', 'string', 'max:160'],
            'plan_name' => ['nullable', 'string', 'max:160'],
            'license_reference' => ['nullable', 'string', 'max:190'],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'seats_total' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(LicenseStatus::class)],
            'billing_cycle' => ['nullable', Rule::enum(LicenseBillingCycle::class)],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'cost_currency' => ['nullable', 'string', 'size:3'],
            'purchased_at' => ['nullable', 'date'],
            'renewal_date' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'auto_renew' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $currency = trim((string) $this->input('cost_currency', ''));

        $this->merge([
            'cost_currency' => $currency !== '' ? strtoupper($currency) : null,
            'auto_renew' => $this->boolean('auto_renew'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $license = $this->route('license');

            if ($license instanceof License) {
                $activeAssignments = $license->assignments()
                    ->where('status', LicenseAssignmentStatus::ACTIVE->value)
                    ->count();

                $targetSeats = (int) $this->input('seats_total', $license->seats_total);

                if ($targetSeats < $activeAssignments) {
                    $validator->errors()->add('seats_total', 'A quantidade total nao pode ficar abaixo das licencas em uso.');
                }
            }

            $purchasedAt = $this->input('purchased_at');
            $renewalDate = $this->input('renewal_date');
            $expiresAt = $this->input('expires_at');

            if ($purchasedAt && $renewalDate && strtotime((string) $renewalDate) < strtotime((string) $purchasedAt)) {
                $validator->errors()->add('renewal_date', 'A renovacao nao pode ser anterior a data de compra.');
            }

            if ($purchasedAt && $expiresAt && strtotime((string) $expiresAt) < strtotime((string) $purchasedAt)) {
                $validator->errors()->add('expires_at', 'A expiracao nao pode ser anterior a data de compra.');
            }

            if ($renewalDate && $expiresAt && strtotime((string) $expiresAt) < strtotime((string) $renewalDate)) {
                $validator->errors()->add('expires_at', 'A expiracao nao pode ser anterior a data de renovacao.');
            }
        });
    }
}
