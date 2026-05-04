<?php

namespace App\Modules\Licenses\Support;

use App\Enums\LicenseAssignmentStatus;
use App\Models\User;
use App\Modules\Licenses\Models\License;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LicenseIndexQuery
{
    public function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->string('search')),
            'vendor_name' => trim((string) $request->string('vendor_name')),
            'product_name' => trim((string) $request->string('product_name')),
            'status' => trim((string) $request->string('status')),
            'sector_id' => $request->integer('sector_id') ?: null,
            'availability' => trim((string) $request->string('availability')),
            'renewal' => trim((string) $request->string('renewal')),
        ];
    }

    public function build(array $filters, User $user): Builder
    {
        $query = License::query()
            ->with(['sector.company', 'creator'])
            ->withCount([
                'assignments as active_assignments_count' => fn (Builder $assignmentQuery) => $assignmentQuery
                    ->where('status', LicenseAssignmentStatus::ACTIVE->value),
            ]);

        if (! $user->isSuperAdmin()) {
            $query->whereIn('sector_id', $user->operationalSectorIds());
        }

        return $query
            ->when($filters['search'] !== '', function (Builder $licenseQuery) use ($filters) {
                $term = '%'.$filters['search'].'%';

                $licenseQuery->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('vendor_name', 'like', $term)
                        ->orWhere('product_name', 'like', $term)
                        ->orWhere('plan_name', 'like', $term)
                        ->orWhere('license_reference', 'like', $term)
                        ->orWhere('supplier_name', 'like', $term)
                        ->orWhereHas('assignments', function (Builder $assignmentQuery) use ($term) {
                            $assignmentQuery
                                ->where('assigned_email', 'like', $term)
                                ->orWhere('display_name', 'like', $term)
                                ->orWhere('external_reference', 'like', $term);
                        });
                });
            })
            ->when($filters['vendor_name'] !== '', fn (Builder $licenseQuery) => $licenseQuery
                ->where('vendor_name', 'like', '%'.$filters['vendor_name'].'%'))
            ->when($filters['product_name'] !== '', fn (Builder $licenseQuery) => $licenseQuery
                ->where('product_name', 'like', '%'.$filters['product_name'].'%'))
            ->when($filters['status'] !== '', fn (Builder $licenseQuery) => $licenseQuery
                ->where('status', $filters['status']))
            ->when($filters['sector_id'], fn (Builder $licenseQuery, int $sectorId) => $licenseQuery
                ->where('sector_id', $sectorId))
            ->when($filters['availability'] === 'available', fn (Builder $licenseQuery) => $licenseQuery
                ->havingRaw('COALESCE(active_assignments_count, 0) < seats_total'))
            ->when($filters['availability'] === 'full', fn (Builder $licenseQuery) => $licenseQuery
                ->havingRaw('COALESCE(active_assignments_count, 0) >= seats_total'))
            ->when($filters['renewal'] === 'expiring', fn (Builder $licenseQuery) => $this->expiringQuery($licenseQuery))
            ->when($filters['renewal'] === 'expired', fn (Builder $licenseQuery) => $this->expiredQuery($licenseQuery))
            ->orderBy('vendor_name')
            ->orderBy('product_name')
            ->orderBy('plan_name');
    }

    private function expiringQuery(Builder $query): Builder
    {
        $today = now()->startOfDay()->toDateString();
        $limit = now()->addDays(30)->endOfDay()->toDateString();

        return $query->where(function (Builder $dateQuery) use ($today, $limit) {
            $dateQuery
                ->whereBetween('expires_at', [$today, $limit])
                ->orWhere(function (Builder $renewalQuery) use ($today, $limit) {
                    $renewalQuery
                        ->whereNull('expires_at')
                        ->whereBetween('renewal_date', [$today, $limit]);
                });
        });
    }

    private function expiredQuery(Builder $query): Builder
    {
        $today = now()->startOfDay()->toDateString();

        return $query->where(function (Builder $dateQuery) use ($today) {
            $dateQuery
                ->whereDate('expires_at', '<', $today)
                ->orWhere(function (Builder $renewalQuery) use ($today) {
                    $renewalQuery
                        ->whereNull('expires_at')
                        ->whereDate('renewal_date', '<', $today);
                });
        });
    }
}
