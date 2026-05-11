<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isGlobalAdmin(), 403);

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'event' => trim((string) $request->query('event', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        $logs = ActivityLog::query()
            ->with(['causer', 'sector', 'subject'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $like = '%'.$search.'%';

                $query->where(function ($searchQuery) use ($like): void {
                    $searchQuery
                        ->where('event', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('properties', 'like', $like)
                        ->orWhereHas('causer', function ($causerQuery) use ($like): void {
                            $causerQuery
                                ->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        })
                        ->orWhereHas('sector', fn ($sectorQuery) => $sectorQuery->where('name', 'like', $like));
                });
            })
            ->when($filters['event'] !== '', fn ($query) => $query->where('event', $filters['event']))
            ->when($filters['date_from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $events = ActivityLog::query()
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        return view('modules.audit.logs.index', [
            'logs' => $logs,
            'events' => $events,
            'filters' => $filters,
        ]);
    }
}
