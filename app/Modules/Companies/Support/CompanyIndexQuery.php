<?php

namespace App\Modules\Companies\Support;

use App\Modules\Companies\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CompanyIndexQuery
{
    public function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->string('search')),
            'status' => trim((string) $request->string('status')),
        ];
    }

    public function build(array $filters): Builder
    {
        return Company::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $query->where(function (Builder $searchQuery) use ($filters) {
                    $searchQuery
                        ->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('legal_name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('document', 'like', '%'.$filters['search'].'%')
                        ->orWhere('email', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->latest();
    }
}
