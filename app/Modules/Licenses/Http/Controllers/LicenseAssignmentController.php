<?php

namespace App\Modules\Licenses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Licenses\Http\Requests\LicenseAssignmentRequest;
use App\Modules\Licenses\Http\Requests\LicenseTransferRequest;
use App\Modules\Licenses\Models\License;
use App\Modules\Licenses\Models\LicenseAssignment;
use App\Modules\Licenses\Services\LicenseAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LicenseAssignmentController extends Controller
{
    public function __construct(
        private readonly LicenseAssignmentService $licenseAssignmentService,
    ) {}

    public function store(LicenseAssignmentRequest $request, License $license): RedirectResponse
    {
        $this->authorize('update', $license);

        $this->licenseAssignmentService->createAssignment($license, $request->validated(), $request->user());

        return redirect()
            ->route('licenses.show', $license)
            ->with('status', 'Licenca atribuida com sucesso.');
    }

    public function update(LicenseAssignmentRequest $request, License $license, LicenseAssignment $assignment): RedirectResponse
    {
        $this->authorize('update', $license);

        $this->licenseAssignmentService->updateAssignment($license, $assignment, $request->validated());

        return redirect()
            ->route('licenses.show', $license)
            ->with('status', 'Atribuicao atualizada com sucesso.');
    }

    public function transfer(LicenseTransferRequest $request, License $license, LicenseAssignment $assignment): RedirectResponse
    {
        $this->authorize('update', $license);

        $this->licenseAssignmentService->transferAssignment($license, $assignment, $request->validated(), $request->user());

        return redirect()
            ->route('licenses.show', $license)
            ->with('status', 'Licenca transferida com sucesso.');
    }

    public function release(Request $request, License $license, LicenseAssignment $assignment): RedirectResponse
    {
        $this->authorize('update', $license);

        $this->licenseAssignmentService->releaseAssignment($license, $assignment, $request->user());

        return redirect()
            ->route('licenses.show', $license)
            ->with('status', 'Licenca desatribuida com sucesso.');
    }
}
