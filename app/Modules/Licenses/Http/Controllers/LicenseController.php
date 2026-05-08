<?php

namespace App\Modules\Licenses\Http\Controllers;

use App\Enums\LicenseAssignmentStatus;
use App\Enums\LicenseBillingCycle;
use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Licenses\Exports\LicensesExport;
use App\Modules\Licenses\Http\Requests\LicenseRequest;
use App\Modules\Licenses\Models\License;
use App\Modules\Licenses\Support\LicenseIndexQuery;
use App\Modules\Sectors\Models\Sector;
use App\Support\Exports\SpreadsheetExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LicenseController extends Controller
{
    public function __construct(
        private readonly LicenseIndexQuery $licenseIndexQuery,
        private readonly SpreadsheetExporter $spreadsheetExporter,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', License::class);

        $filters = $this->licenseIndexQuery->filters($request);
        $licenses = $this->licenseIndexQuery
            ->build($filters, $request->user())
            ->paginate(12)
            ->withQueryString();

        return view('modules.licenses.index', [
            'licenses' => $licenses,
            'filters' => $filters,
            'statuses' => LicenseStatus::cases(),
            'sectors' => $this->availableSectors($request->user()),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', License::class);

        $filters = $this->licenseIndexQuery->filters($request);
        $export = new LicensesExport(
            $this->licenseIndexQuery->build($filters, $request->user())->get(),
        );

        return $this->spreadsheetExporter->download($export->fileName(), $export->sheets());
    }

    public function create(Request $request): View
    {
        $this->authorize('create', License::class);

        return view('modules.licenses.create', [
            'license' => new License,
            ...$this->formData($request->user()),
        ]);
    }

    public function store(LicenseRequest $request): RedirectResponse
    {
        $this->authorize('create', License::class);

        $license = License::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('licenses.show', $license)
            ->with('status', 'Licenca cadastrada com sucesso.');
    }

    public function show(License $license): View
    {
        $this->authorize('view', $license);

        $license->load([
            'sector.company',
            'creator',
            'assignments.user',
            'assignments.creator',
        ])->loadCount([
            'assignments as active_assignments_count' => fn ($assignmentQuery) => $assignmentQuery
                ->where('status', LicenseAssignmentStatus::ACTIVE->value),
        ]);

        return view('modules.licenses.show', [
            'license' => $license,
            'assignments' => $license->assignments
                ->filter(fn ($assignment) => $assignment->status === LicenseAssignmentStatus::ACTIVE)
                ->sortBy(fn ($assignment) => implode('|', [
                    $assignment->resolvedDisplayName(),
                    $assignment->resolvedAssignedEmail() ?? '',
                ]))
                ->values(),
            'collaborators' => $this->assignmentCandidates(),
            'activityLogs' => $license->activityLogs()
                ->with('causer')
                ->limit(12)
                ->get(),
        ]);
    }

    public function edit(Request $request, License $license): View
    {
        $this->authorize('update', $license);

        return view('modules.licenses.edit', [
            'license' => $license,
            ...$this->formData($request->user()),
        ]);
    }

    public function update(LicenseRequest $request, License $license): RedirectResponse
    {
        $this->authorize('update', $license);

        $license->update($request->validated());

        return redirect()
            ->route('licenses.show', $license)
            ->with('status', 'Licenca atualizada com sucesso.');
    }

    private function formData(User $user): array
    {
        return [
            'statuses' => LicenseStatus::cases(),
            'billingCycles' => LicenseBillingCycle::cases(),
            'sectors' => $this->availableSectors($user),
        ];
    }

    private function availableSectors(User $user)
    {
        $query = Sector::query()->with('company')->orderBy('name');

        if (! $user->isGlobalAdmin()) {
            $query->whereRaw('1 = 0');
        }

        return $query->get();
    }

    private function assignmentCandidates()
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('email')
            ->get();
    }
}
